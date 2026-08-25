<?php

namespace App\Http\Requests\Booking;

use App\Models\Equipment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.equipment_id' => [
                'required',
                'integer',
                Rule::exists(Equipment::class, 'id'),
                'distinct',
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.equipment_id.distinct' => 'Tidak boleh ada alat yang sama dikirim dua kali.',
        ];
    }
}
