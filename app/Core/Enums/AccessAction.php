<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum AccessAction: string
{
    use EnumValues;

    case ViewRecord         = 'view_record';
    case ViewHistory        = 'view_history';
    case ViewMedications    = 'view_medications';
    case ViewAttachments    = 'view_attachments';
    case CreateEncounter    = 'create_encounter';
    case CreateNote         = 'create_note';
    case CreatePrescription = 'create_prescription';
    case ExportRecord       = 'export_record';

    /**
     * هل هذا الإجراء يتطلب AccessType::Full ؟
     * يُستخدَم في AccessGuard Middleware (المرحلة 5)
     */
    public function requiresFullAccess(): bool
    {
        return in_array($this, [
            self::CreateEncounter,
            self::CreateNote,
            self::CreatePrescription,
        ], true);
    }
}
