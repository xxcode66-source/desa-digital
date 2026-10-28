<?php
namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * Menerjemahkan form_schema_json sebuah jenis surat menjadi aturan validasi Laravel
 * (PEMBANGUNAN.md 4.2 — jenis surat dinamis tanpa ubah kode). Nilai disimpan sebagai
 * string di letters.data_json; field bertipe select diverifikasi terhadap opsinya.
 */
class LetterFormValidator
{
    /** @param array<int, array{key:string,label?:string,type?:string,required?:bool,options?:array}> $schema */
    public static function rules(array $schema): array
    {
        $rules = [];

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            if (!$key) {
                continue;
            }

            $chain = [(empty($field['required']) ? 'nullable' : 'required'), 'string', 'max:2000'];

            if (($field['type'] ?? 'text') === 'select' && !empty($field['options'])) {
                $chain[] = Rule::in($field['options']);
            }

            $rules['data.' . $key] = $chain;
        }

        return $rules;
    }

    /** Pesan error berbahasa Indonesia sederhana memakai label field. */
    public static function messages(array $schema): array
    {
        $messages = [];

        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            if (!$key) {
                continue;
            }
            $label = $field['label'] ?? $key;
            $messages['data.' . $key . '.required'] = "Isi bagian: $label.";
            $messages['data.' . $key . '.in'] = "Pilihan untuk $label tidak valid.";
        }

        return $messages;
    }
}
