<?php

namespace Modules\TripManagement\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class RideRequestCreate extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'pickup_coordinates' => 'required_if:trip_request_id,null',
            'destination_coordinates' => 'required_if:trip_request_id,null',
            'customer_coordinates' => 'required_if:trip_request_id,null',
            'customer_request_coordinates' => 'required_if:trip_request_id,null',
            'pickup_address' => 'required_if:trip_request_id,null',
            'destination_address' => 'required_if:trip_request_id,null',

            // Trip and Fare Information
            'estimated_distance' => 'required_if:trip_request_id,null',
            'estimated_time' => 'required_if:trip_request_id,null',
            // estimated_fare / actual_fare are IGNORED from the request body — fares are always
            // recomputed server-side. Keep them as optional/nullable so old clients don't break.
            'estimated_fare' => 'sometimes|nullable|numeric|max:99999999',
            'bid' => 'required|bool',
            'actual_fare' => ['sometimes', 'nullable', 'numeric', 'max:99999999'],

            // Return and Cancellation Fees
            'return_fee' => [
                Rule::requiredIf(function () {
                    return $this->type === 'parcel' && is_null($this->trip_request_id);
                })
            ],
            'cancellation_fee' => [
                Rule::requiredIf(function () {
                    return $this->type === 'parcel' && is_null($this->trip_request_id);
                })
            ],

            // Vehicle Category
            'vehicle_category_id' => [
                'nullable',
                Rule::requiredIf(function ()  {
                    return $this->type === 'ride_request' && is_null($this->trip_request_id);
                }), 'uuid'
            ],

            // Other Fields
            'note' => 'sometimes',
            'type' => 'required|in:parcel,ride_request',
            'ride_request_type' => [Rule::requiredIf($this->input('type') === RIDE_REQUEST), 'nullable', Rule::in(['regular', 'scheduled'])],
            'scheduled_at' => 'required_if:ride_request_type,scheduled',
            'pickup_note' => 'nullable|string|max:100',
            'delivery_notes' => 'nullable|string|max:200',

            // Sender and Receiver Information (required only for parcel type)
            'sender_name' => 'required_if:type,parcel',
            'sender_phone' => 'required_if:type,parcel',
            'sender_address' => 'required_if:type,parcel',
            'receiver_name' => 'required_if:type,parcel',
            'receiver_phone' => 'required_if:type,parcel',
            'receiver_address' => 'required_if:type,parcel',

            // Parcel Information (required for parcel type)
            'parcel_category_id' => 'required_if:type,parcel',
            'weight' => 'required_if:type,parcel',
            'payer' => 'required_if:type,parcel',

            // Additional Fare Fields — all ignored server-side, kept for backward compat with old clients
            'extra_estimated_fare' => 'sometimes|nullable|numeric',
            'extra_discount_fare' => 'sometimes|numeric',
            'extra_discount_amount' => 'sometimes|numeric',
            'extra_return_fee' => 'sometimes|numeric',
            'extra_cancellation_fee' => 'sometimes|numeric',
            'extra_fare_amount' => 'sometimes|numeric',
            'extra_fare_fee' => 'sometimes|numeric',

            // Encoded Polyline and Zone Information
            'encoded_polyline' => 'sometimes',
            'zone_id' => 'required|uuid|exists:zones,id',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json(
                responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)),
                403
            )
        );
    }
}
