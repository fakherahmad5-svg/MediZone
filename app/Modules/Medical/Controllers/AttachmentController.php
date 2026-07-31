<?php

namespace App\Modules\Medical\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Core\Traits\ResolvesPatientRecord;
use App\Models\Attachment;
use App\Modules\Medical\Requests\StoreAttachmentRequest;
use App\Modules\Medical\Resources\AttachmentResource;
use App\Modules\Medical\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;


class AttachmentController extends BaseController
{
    use ResolvesPatientRecord;

    public function __construct(
        private readonly AttachmentService $attachments
    ) {}

    public function index(Request $request): JsonResponse
    {
        $patientRecord = $this->resolvePatientRecord($request->user());

        return $this->successResponse(
            AttachmentResource::collection($this->attachments->listForPatientRecord($patientRecord)),
            'Attachments retrieved successfully.'
        );
    }

    public function store(StoreAttachmentRequest $request): JsonResponse
    {
        $patientRecord = $this->resolvePatientRecord($request->user());

        $attachment = $this->attachments->upload(
            $patientRecord,
            $request->user(),
            $request->file('file'),
            $request->validated('type')
        );

        return $this->createdResponse(
            new AttachmentResource($attachment),
            'Attachment uploaded successfully.'
        );
    }

    public function download(Request $request, int $id): Response
    {
        $patientRecord = $this->resolvePatientRecord($request->user());
        $attachment    = Attachment::findOrFail($id);

        $file = $this->attachments->download($patientRecord, $attachment);

        return response($file['contents'], 200, [
            'Content-Type'        => $file['mime_type'],
            'Content-Disposition' => 'attachment; filename="' . $file['file_name'] . '"',
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $patientRecord = $this->resolvePatientRecord($request->user());
        $attachment    = Attachment::findOrFail($id);

        $this->attachments->delete($patientRecord, $attachment);

        return $this->noContentResponse();
    }
}
