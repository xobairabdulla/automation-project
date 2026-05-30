<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'source_type' => ['sometimes', 'in:woocommerce_api,shopify_api,custom_api,manual'],
            'website_url' => ['nullable', 'url', 'max:500'],
            'api_url' => ['nullable', 'url', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'api_secret' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'product_image_reply_enabled' => ['boolean'],
            'max_images_per_reply' => ['integer', 'min:1', 'max:10'],
        ];
    }
}
