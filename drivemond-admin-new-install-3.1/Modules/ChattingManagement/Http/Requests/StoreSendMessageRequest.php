<?php

namespace Modules\ChattingManagement\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreSendMessageRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $api = str_contains($this->route()->getPrefix(), 'api');
        return [
            'channel_id' => [Rule::requiredIf(function () use($api) {
                return $api;
            })],
            'channelId' => [Rule::requiredIf(function () use($api) {
                return !$api;
            })],
            'trip_id' => 'sometimes',
            'order_id' => 'sometimes|uuid',
            'message' => $api ? 'required_without:files' : [Rule::requiredIf(function () {
                return !$this->has('file') && !$this->has('image');
            })],
            'files' => 'sometimes|array',
            'files.*' => 'mimes:'
                . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS)
                . ','
                . str_replace(['.', ' '], '', FILE_ACCEPTED_EXTENSIONS)
                . '|max:' . convertBytesToKiloBytes(maxUploadSize('file')),
            'file' => 'sometimes|array',
            'file.*' => 'mimes:'
                . str_replace(['.', ' '], '', FILE_ACCEPTED_EXTENSIONS)
                . ','
                . '|max:' . convertBytesToKiloBytes(maxUploadSize('file')),
            'image' => 'sometimes|array',
            'image.*' => 'mimes:'
                . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS)
                . '|max:' . convertBytesToKiloBytes(maxUploadSize('file')),

        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function messages()
    {
        return [
            'files.*.max' => translate(key: 'Each document must be less than {maxSize}', replace: ['maxSize' => readableUploadMaxFileSize('image')]),
        ];
    }

    protected function prepareForValidation()
    {
        showValidationMessageForUploadMaxSize(files: $this->allFiles(), isAjax: $this->ajax(), doesExpectJson: $this->expectsJson());
    }

    protected function failedValidation(Validator $validator)
    {
        $api = str_contains($this->route()->getPrefix(), 'api');
        $error = $validator->errors()->toArray();
        $key = key($error);
        $message = $error[$key][0] ?? null;
        $fieldName = str_contains($key, '.') ? explode('.', $key)[0] : $key;

        throw new HttpResponseException(response()->json([
            'errors' => [['error_code' => $fieldName, 'message' => $message]],
        ], $api ? 403 : 200));
    }
}
