<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampaignImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CampaignImageController extends Controller
{
    public function index(): JsonResponse
    {
        $campaignId = request()->query('campaign_id');

        $query = CampaignImage::query()->orderByDesc('id');

        if ($campaignId !== null && $campaignId !== '') {
            $query->where('campaign_id', $campaignId);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        try {
            if ($request->hasFile('image')) {
                $validated = $request->validate([
                    'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
                    'image' => ['required', 'image', 'max:5120'],
                ]);

                $path = $this->storeUploadedImage($request->file('image'));

                $record = CampaignImage::create([
                    'campaign_id' => $validated['campaign_id'],
                    'image_path' => $path,
                ]);

                return response()->json($record, 201);
            }

            $validated = $request->validate([
                'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
                'image_path' => ['required', 'string', 'max:255'],
            ]);

            $record = CampaignImage::create($validated);

            return response()->json($record, 201);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Failed to upload campaign image.',
                'error' => app()->hasDebugModeEnabled() ? $exception->getMessage() : null,
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        $record = CampaignImage::findOrFail($id);

        return response()->json($record);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $record = CampaignImage::findOrFail($id);
            if ($request->hasFile('image')) {
                $validated = $request->validate([
                    'campaign_id' => ['sometimes', 'integer', 'exists:campaigns,id'],
                    'image' => ['required', 'image', 'max:5120'],
                ]);

                $this->deleteImagePath($record->image_path);

                $path = $this->storeUploadedImage($request->file('image'));
                $record->update([
                    'campaign_id' => $validated['campaign_id'] ?? $record->campaign_id,
                    'image_path' => $path,
                ]);

                return response()->json($record);
            }

            $validated = $request->validate([
                'campaign_id' => ['sometimes', 'integer', 'exists:campaigns,id'],
                'image_path' => ['sometimes', 'string', 'max:255'],
            ]);

            $record->update($validated);

            return response()->json($record);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Failed to update campaign image.',
                'error' => app()->hasDebugModeEnabled() ? $exception->getMessage() : null,
            ], 500);
        }
    }

    public function destroy(int $id): Response
    {
        $record = CampaignImage::findOrFail($id);
        $this->deleteImagePath($record->image_path);
        $record->delete();

        return response()->noContent();
    }

    private function storeUploadedImage(UploadedFile $file): string
    {
        try {
            $stored = $file->store('campaigns', 'public');
            if (is_string($stored) && $stored !== '') {
                return $stored;
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        $directory = public_path('uploads/campaigns');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $file->move($directory, $filename);

        return 'uploads/campaigns/'.$filename;
    }

    private function deleteImagePath(?string $path): void
    {
        if (!$path) {
            return;
        }

        if (str_starts_with($path, 'uploads/')) {
            $absolutePath = public_path($path);
            if (File::exists($absolutePath)) {
                File::delete($absolutePath);
            }

            return;
        }

        Storage::disk('public')->delete($path);
    }
}
