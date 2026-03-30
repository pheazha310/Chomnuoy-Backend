<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $records = AdminSetting::query()
            ->orderBy('section')
            ->orderBy('setting_key')
            ->get();

        return response()->json([
            'data' => $records->map(fn (AdminSetting $record) => $this->transform($record))->values(),
            'map' => $this->toMap($records),
        ]);
    }

    public function getBySection(string $section): JsonResponse
    {
        $records = AdminSetting::query()
            ->where('section', $section)
            ->orderBy('setting_key')
            ->get();

        return response()->json([
            'section' => $section,
            'data' => $records->map(fn (AdminSetting $record) => $this->transform($record))->values(),
            'map' => $this->toMap($records),
        ]);
    }

    public function show(string $key): JsonResponse
    {
        $record = AdminSetting::query()->where('setting_key', $key)->firstOrFail();

        return response()->json($this->transform($record));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['nullable', 'string', 'max:191'],
            'section' => ['nullable', 'string', 'max:100'],
            'value' => ['nullable'],
            'settings' => ['nullable', 'array'],
            'settings.*.key' => ['required_with:settings', 'string', 'max:191'],
            'settings.*.section' => ['nullable', 'string', 'max:100'],
            'settings.*.value' => ['nullable'],
        ]);

        $entries = [];
        if (!empty($validated['settings']) && is_array($validated['settings'])) {
            $entries = $validated['settings'];
        } elseif (!empty($validated['key'])) {
            $entries[] = [
                'key' => $validated['key'],
                'section' => $validated['section'] ?? null,
                'value' => $validated['value'] ?? null,
            ];
        }

        $saved = collect($entries)->map(function (array $entry) {
            [$encodedValue, $settingType] = $this->encodeValueAndType($entry['value'] ?? null);
            $record = AdminSetting::query()->updateOrCreate(
                ['setting_key' => $entry['key']],
                [
                    'section' => $entry['section'] ?? null,
                    'setting_value' => $encodedValue,
                    'setting_type' => $settingType,
                ]
            );

            return $this->transform($record);
        })->values();

        return response()->json([
            'message' => 'Settings saved successfully.',
            'data' => $saved,
        ], 201);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'value' => ['nullable'],
            'section' => ['nullable', 'string', 'max:100'],
        ]);

        [$encodedValue, $settingType] = $this->encodeValueAndType($validated['value'] ?? null);
        $record = AdminSetting::query()->updateOrCreate(
            ['setting_key' => $key],
            [
                'section' => $validated['section'] ?? null,
                'setting_value' => $encodedValue,
                'setting_type' => $settingType,
            ]
        );

        return response()->json($this->transform($record));
    }

    public function destroy(string $key): JsonResponse
    {
        $record = AdminSetting::query()->where('setting_key', $key)->firstOrFail();
        $record->delete();

        return response()->json(null, 204);
    }

    private function transform(AdminSetting $record): array
    {
        return [
            'id' => $record->id,
            'key' => $record->setting_key,
            'section' => $record->section,
            'value' => $this->decodeValue($record->setting_value, $record->setting_type),
            'type' => $record->setting_type,
            'description' => $record->description,
            'created_at' => $record->created_at,
            'updated_at' => $record->updated_at,
        ];
    }

    private function toMap($records): array
    {
        $map = [];
        foreach ($records as $record) {
            $map[$record->setting_key] = $this->decodeValue($record->setting_value, $record->setting_type);
        }

        return $map;
    }

    private function encodeValueAndType(mixed $value): array
    {
        if ($value === null) {
            return [null, 'string'];
        }

        if (is_bool($value)) {
            return [$value ? '1' : '0', 'boolean'];
        }

        if (is_int($value) || is_float($value)) {
            return [(string) $value, 'number'];
        }

        if (is_string($value)) {
            return [$value, 'string'];
        }

        return [json_encode($value), 'json'];
    }

    private function decodeValue(?string $value, ?string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($type === 'boolean') {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        if ($type === 'number') {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        if ($type === 'json') {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }
}
