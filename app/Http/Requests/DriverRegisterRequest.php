<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DriverRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Doit être TRUE
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'birth_date' => 'required|date',
            'address' => 'required|string|max:500',
            'phone' => 'required|string|unique:drivers,phone',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'vehicle_type' => 'required|in:voiture,moto,camion',
            'license_plate' => 'required|string|max:20',
            'cni_photo' => 'required|image|max:2048', // 2MB max
        ];
    }

    public function messages(): array
    {
        return [
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
            'email.unique' => 'Cet email est déjà utilisé.',
            'cni_photo.required' => 'La photo CNI est obligatoire.',
        ];
    }
}
