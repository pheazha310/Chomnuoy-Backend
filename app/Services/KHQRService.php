<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class KHQRService
{
    protected string $apiUrl;
    protected string $token;
    protected array $merchant;

    // Expiry in minutes for dynamic QR codes
    const QR_EXPIRY_MINUTES = 30;

    public function __construct()
    {
        $this->apiUrl = config('services.bakong.api_url') ?? 'https://api-bakong.nbc.gov.kh';
        $this->token  = config('services.bakong.token') ?? '';
        $this->merchant = config('services.bakong.merchant') ?? [];
    }

    /**
     * Generate Individual KHQR String locally
     */
    public function generateIndividualQR(array $data): array
    {
        try {
            $qrString = $this->buildKHQRString($data);
            $md5 = md5($qrString);

            return [
                'data' => [
                    'qr'  => $qrString,
                    'md5' => $md5,
                ],
                'status' => ['code' => 0],
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function generateMerchantQR(array $data): array
    {
        return $this->generateIndividualQR($data);
    }

    /**
     * Build KHQR String per EMV QR + NBC KHQR spec (latest).
     *
     * Key changes vs old version:
     *  - Tag 99 now carries both creation timestamp (sub-tag 00)
     *    AND expiration timestamp (sub-tag 01) in milliseconds.
     *  - expirationTimestamp is required for dynamic (amount > 0) QR codes.
     */
    protected function buildKHQRString(array $data): string
    {
        $bakongId     = $data['bakong_account_id'] ?? $this->merchant['bakong_id'] ?? '';
        $amount       = $data['amount'] ?? 0;
        $currency     = $data['currency'] ?? 'USD';
        $merchantName = $data['merchant_name'] ?? $this->merchant['name'] ?? 'Merchant';
        $merchantCity = $data['merchant_city'] ?? $this->merchant['city'] ?? 'PHNOM PENH';
        $billNumber   = $data['bill_number'] ?? '';
        $mobileNumber = $data['mobile_number'] ?? '';
        $storeLabel   = $data['store_label'] ?? '';
        $terminalLabel = $data['terminal_label'] ?? '';
        $acquiringBank = $data['acquiring_bank'] ?? $this->merchant['acquiring_bank'] ?? '';

        // Timestamps in milliseconds
        $nowMs        = (int) round(microtime(true) * 1000);
        $expiryMs     = $data['expiration_timestamp'] ?? ($nowMs + self::QR_EXPIRY_MINUTES * 60 * 1000);

        $qr = '';

        // ID 00 – Payload Format Indicator
        $qr .= $this->tlv('00', '01');

        // ID 01 – Point of Initiation: 11 = static, 12 = dynamic
        $qr .= $this->tlv('01', ($amount > 0) ? '12' : '11');

        // ID 29 – Merchant Account Information (Individual / Bakong)
        $merchantAccount = $this->tlv('00', $bakongId);
        if (!empty($mobileNumber)) {
            $merchantAccount .= $this->tlv('01', $mobileNumber);
        }
        if (!empty($acquiringBank)) {
            $merchantAccount .= $this->tlv('02', $acquiringBank);
        }
        $qr .= $this->tlv('29', $merchantAccount);

        // ID 52 – Merchant Category Code
        $qr .= $this->tlv('52', $data['merchant_category_code'] ?? '5999');

        // ID 53 – Transaction Currency: 840 = USD, 116 = KHR
        $qr .= $this->tlv('53', $currency === 'KHR' ? '116' : '840');

        // ID 54 – Transaction Amount (only for dynamic QR)
        if ($amount > 0) {
            $qr .= $this->tlv('54', number_format((float) $amount, 2, '.', ''));
        }

        // ID 58 – Country Code
        $qr .= $this->tlv('58', 'KH');

        // ID 59 – Merchant Name (max 25 chars)
        $qr .= $this->tlv('59', substr($merchantName, 0, 25));

        // ID 60 – Merchant City (max 15 chars)
        $qr .= $this->tlv('60', substr($merchantCity, 0, 15));

        // ID 62 – Additional Data Field Template
        $additionalData = '';
        if (!empty($billNumber)) {
            $additionalData .= $this->tlv('01', $billNumber);
        }
        if (!empty($mobileNumber)) {
            $additionalData .= $this->tlv('02', $mobileNumber);
        }
        if (!empty($storeLabel)) {
            $additionalData .= $this->tlv('03', $storeLabel);
        }
        if (!empty($terminalLabel)) {
            $additionalData .= $this->tlv('07', $terminalLabel);
        }
        if (!empty($additionalData)) {
            $qr .= $this->tlv('62', $additionalData);
        }

        // ID 99 – Timestamp (NBC KHQR spec)
        //   Sub-tag 00: creation timestamp in ms
        //   Sub-tag 01: expiration timestamp in ms (required for dynamic QR)
        $timestampData = $this->tlv('00', (string) $nowMs);
        if ($amount > 0) {
            $timestampData .= $this->tlv('01', (string) $expiryMs);
        }
        $qr .= $this->tlv('99', $timestampData);

        // ID 63 – CRC (placeholder then calculated)
        $qr .= '6304';
        $crc = $this->crc16($qr);
        $qr  = substr($qr, 0, -4) . '6304' . strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));

        return $qr;
    }

    /**
     * TLV (Tag-Length-Value) helper
     */
    protected function tlv(string $tag, string $value): string
    {
        return $tag . str_pad(strlen($value), 2, '0', STR_PAD_LEFT) . $value;
    }

    /**
     * CRC16-CCITT (FALSE) checksum
     */
    protected function crc16(string $data): int
    {
        $crc = 0xFFFF;
        $poly = 0x1021;
        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000)
                    ? (($crc << 1) ^ $poly) & 0xFFFF
                    : ($crc << 1) & 0xFFFF;
            }
        }
        return $crc;
    }

    // -------------------------------------------------------------------------
    // Bakong API calls
    // -------------------------------------------------------------------------

    /**
     * Check if a single transaction is paid.
     * Returns "SUCCESS" | "PENDING" | error info.
     */
    public function checkPayment(string $md5): array
    {
        return $this->apiPost('/v1/check_transaction_by_md5', ['md5' => $md5]);
    }

    /**
     * Retrieve full transaction details for a paid QR.
     * Useful for static QR where amount is unknown until payment.
     */
    public function getPayment(string $md5): array
    {
        return $this->apiPost('/v1/get_transaction_by_md5', ['md5' => $md5]);
    }

    /**
     * Check multiple transactions at once (max 50 per request).
     * Returns only the MD5 hashes that are paid.
     */
    public function checkBulkPayments(array $md5List): array
    {
        if (count($md5List) > 50) {
            return ['error' => 'Bulk check limit is 50 MD5 hashes per request.'];
        }

        return $this->apiPost('/v1/check_transaction_by_md5_list', ['md5' => $md5List]);
    }

    /**
     * Generate a Bakong deep link for the QR string.
     * Optionally pass callback URL, app icon URL, and app name.
     */
    public function generateDeepLink(string $qrCode, array $options = []): array
    {
        try {
            $payload = ['qr' => $qrCode];

            if (!empty($options['callback'])) {
                $payload['sourceInfo'] = [
                    'appIconUrl'  => $options['appIconUrl'] ?? '',
                    'appName'     => $options['appName'] ?? '',
                    'appDeepLink' => $options['callback'],
                ];
            }

            $response = $this->apiPost('/v1/generate_deeplink_by_qr', $payload);

            if (isset($response['data']['shortLink'])) {
                return [
                    'success'    => true,
                    'deep_link'  => $response['data']['shortLink'],
                    'raw'        => $response,
                ];
            }

            // Fallback: local deep links
            $encoded = urlencode($qrCode);
            return [
                'success'    => true,
                'deep_links' => [
                    'bakong'  => "bakong://qr?data={$encoded}",
                    'aba'     => "aba://qr?data={$encoded}",
                    'acleda'  => "acleda://qr?data={$encoded}",
                    'generic' => "khqr://pay?data={$encoded}",
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Verify QR code CRC integrity
     */
    public function verifyQR(string $qrCode): array
    {
        if (empty($qrCode)) {
            return ['success' => false, 'message' => 'QR code is empty'];
        }
        if (!str_starts_with($qrCode, '00')) {
            return ['success' => false, 'message' => 'Invalid QR format'];
        }
        if (strlen($qrCode) < 8) {
            return ['success' => false, 'message' => 'QR code too short'];
        }

        $crcFromQR   = substr($qrCode, -4);
        $dataForCRC  = substr($qrCode, 0, -4) . '6304';
        $calculated  = strtoupper(str_pad(dechex($this->crc16($dataForCRC)), 4, '0', STR_PAD_LEFT));

        if ($crcFromQR !== $calculated) {
            return ['success' => false, 'message' => 'Invalid CRC checksum'];
        }

        return ['success' => true, 'message' => 'QR code is valid'];
    }

    /**
     * Decode QR code TLV fields
     */
    public function decodeQR(string $qrCode): array
    {
        try {
            $data = [];
            $pos  = 0;
            $len  = strlen($qrCode);

            while ($pos + 4 <= $len - 4) { // -4 to skip CRC
                $tag   = substr($qrCode, $pos, 2);
                $vLen  = (int) substr($qrCode, $pos + 2, 2);
                $value = substr($qrCode, $pos + 4, $vLen);
                $data[$tag] = $value;
                $pos += 4 + $vLen;
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // Internal HTTP helper
    // -------------------------------------------------------------------------

    protected function apiPost(string $path, array $body): array
    {
        try {
            $response = Http::withOptions([
                'verify'  => false,
                'timeout' => 30,
            ])->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type'  => 'application/json',
            ])->post($this->apiUrl . $path, $body);

            if ($response->successful()) {
                return $response->json() ?? ['error' => 'Empty response'];
            }

            return [
                'error'       => 'HTTP ' . $response->status(),
                'body'        => $response->body(),
                'status_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
