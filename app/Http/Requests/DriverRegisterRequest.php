<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DriverRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
    return [
        'first_name' => 'required|string|max:50',
        'last_name' => 'required|string|max:50',
        'birth_date' => 'required|date|before:-18 years',
        'address' => 'required|string|max:255',
        'phone' => 'required|string|max:20',
        'cni_photo' => 'required|image|mimes:jpeg,png,jpg|max:2048', // COMMENTEZ CETTE LIGNE TEMPORAIREMENT
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:8|confirmed',
        'vehicle_type' => 'nullable|string|max:50',
        'license_plate' => 'nullable|string|max:20',
    ];
    }

    public function messages(): array
    {
        return [
            'birth_date.before' => 'Vous devez avoir au moins 18 ans.',
            'cni_photo.required' => 'La photo de la CNI est obligatoire.',
        ];
    }
}