<?php

namespace App\Modules\Medical\Services;

use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\Attachment;
use App\Models\PatientRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class AttachmentService extends BaseService
{
    private const DISK = 'local';

    public function listForPatientRecord(PatientRecord $patientRecord): Collection
    {
        return $patientRecord->attachments()->latest('id')->get();
    }

    public function upload(PatientRecord $patientRecord, User $uploader, UploadedFile $file, ?string $type = null): Attachment
    {
        $originalContents = file_get_contents($file->getRealPath());

        if ($originalContents === false) {
            throw new BusinessException('Failed to read the uploaded file.');
        }

        $checksum = hash('sha256', $originalContents);
        $encrypted = Crypt::encrypt($originalContents);

        $path = 'medical-attachments/' . $patientRecord->id . '/' . Str::uuid() . '.enc';

        $stored = Storage::disk(self::DISK)->put($path, $encrypted);

        if (! $stored) {
            throw new BusinessException('Failed to store the attachment. Please try again.');
        }

        return $this->transaction(function () use ($patientRecord, $uploader, $file, $type, $path, $checksum) {
            return Attachment::create([
                'patient_record_id' => $patientRecord->id,
                'uploaded_by'       => $uploader->id,
                'file_path'         => $path,
                'type'              => $type,
                'mime_type'         => $file->getClientMimeType(),
                'file_size'         => $file->getSize(),
                'checksum'          => $checksum,
                'is_encrypted'      => true,
            ]);
        });
    }


    public function download(PatientRecord $patientRecord, Attachment $attachment): array
    {
        if ($attachment->patient_record_id !== $patientRecord->id) {
            throw new NotFoundException('Attachment not found in your record.');
        }

        $encrypted = Storage::disk(self::DISK)->get($attachment->file_path);

        if ($encrypted === null) {
            throw new BusinessException('The attachment file is missing from storage.');
        }

        $decrypted = Crypt::decrypt($encrypted);

        if (hash('sha256', $decrypted) !== $attachment->checksum) {
            $this->logError('AttachmentService::download', new \RuntimeException('Checksum mismatch'), [
                'attachment_id' => $attachment->id,
            ]);

            throw new BusinessException(
                'File integrity check failed. This attachment may be corrupted.'
            );
        }

        return [
            'contents'  => $decrypted,
            'mime_type' => $attachment->mime_type ?? 'application/octet-stream',
            'file_name' => basename($attachment->file_path, '.enc'),
        ];
    }


    public function delete(PatientRecord $patientRecord, Attachment $attachment): void
    {
        if ($attachment->patient_record_id !== $patientRecord->id) {
            throw new NotFoundException('Attachment not found in your record.');
        }

        $this->transaction(function () use ($attachment) {
            Storage::disk(self::DISK)->delete($attachment->file_path);
            $attachment->delete();
        });
    }
}
