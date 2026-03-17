<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create_campaign');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255',
            'type' => 'required|in:text,media,location,template',
            'body' => 'nullable|string|max:4096',
            'media_path' => 'nullable|string',
            'referrer_id' => 'nullable|integer|exists:referrers,id',
            'referidor_pregonero_id' => 'nullable|integer|exists:referidores_pregoneros,id',
            'source' => 'required|in:twilio,wppconnect',
            'recipients' => 'required|array|min:1',
            'recipients.*.phone' => 'required|string|regex:/^[\d\+\-\s]{7,20}$/',
            'recipients.*.name' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la campaña es obligatorio.',
            'name.min' => 'El nombre debe tener al menos 3 caracteres.',
            'type.required' => 'Debes seleccionar un tipo de campaña.',
            'type.in' => 'Tipo de campaña inválido.',
            'recipients.required' => 'Debes agregar al menos un destinatario.',
            'recipients.*.phone.regex' => 'Formato de teléfono inválido.',
            'source.required' => 'Debes seleccionar la fuente de envío (Twilio o WPPConnect).',
        ];
    }
}
