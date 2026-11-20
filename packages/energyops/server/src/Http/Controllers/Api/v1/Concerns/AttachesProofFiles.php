<?php

namespace Fleetbase\EnergyOps\Http\Controllers\Api\v1\Concerns;

use Fleetbase\Http\Resources\File as FileResource;
use Fleetbase\Models\File;
use Fleetbase\Support\Utils;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Proof attachments (factura, boleta de carga, fotos, firma) for load plans and descargas
 * through the API-key API. Accepts a multipart `file` or a base64 `data` string.
 */
trait AttachesProofFiles
{
    /**
     * @param Model  $subject      load plan or unload the file belongs to
     * @param string $defaultType  file type when none is provided (`load_photo`, `unload_photo`…)
     * @param array  $allowedTypes accepted file types
     */
    protected function attachProofFile(Request $request, Model $subject, string $path, string $defaultType, array $allowedTypes)
    {
        $type = (string) $request->input('type', $defaultType);
        if (!in_array($type, $allowedTypes, true)) {
            $type = $defaultType;
        }

        $disk   = $request->input('disk', config('filesystems.default'));
        $bucket = $request->input('bucket', config('filesystems.disks.' . $disk . '.bucket', config('filesystems.disks.s3.bucket')));

        if ($request->hasFile('file')) {
            $fileName = File::randomFileNameFromRequest($request);
            try {
                $stored = $request->file('file')->storeAs($path, $fileName, ['disk' => $disk]);
                $file   = File::createFromUpload($request->file('file'), $stored, $type, $request->file('file')->getSize(), $disk, $bucket);
            } catch (\Throwable $e) {
                return response()->apiError($e->getMessage());
            }
        } elseif ($request->filled('data')) {
            $data        = (string) $request->input('data');
            $contentType = (string) $request->input('content_type', 'image/png');
            $extension   = Str::afterLast($contentType, '/') ?: 'png';
            $fileName    = (string) ($request->input('file_name') ?: Str::uuid() . '.' . $extension);
            $fullPath    = trim($path, '/') . '/' . $fileName;

            $raw = base64_decode(Str::contains($data, ',') ? Str::after($data, ',') : $data, true);
            if ($raw === false) {
                return response()->apiError('Invalid base64 payload.', 400);
            }

            try {
                Storage::disk($disk)->put($fullPath, $raw);
                $file = File::create([
                    'company_uuid'      => session('company'),
                    'uploader_uuid'     => session('user'),
                    'disk'              => $disk,
                    'original_filename' => $fileName,
                    'extension'         => $extension,
                    'content_type'      => $contentType,
                    'path'              => $fullPath,
                    'bucket'            => $bucket,
                    'type'              => $type,
                    'size'              => strlen($raw),
                ]);
            } catch (\Throwable $e) {
                return response()->apiError($e->getMessage());
            }
        } else {
            return response()->apiError('Provide a `file` upload or a base64 `data` string.', 400);
        }

        $file->update([
            'subject_uuid' => $subject->uuid,
            'subject_type' => Utils::getMutationType($subject),
        ]);

        return new FileResource($file);
    }
}
