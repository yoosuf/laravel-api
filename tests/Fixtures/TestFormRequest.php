<?php

namespace Yoosuf\LaravelApi\Tests\Fixtures;

use Illuminate\Foundation\Http\FormRequest;

class TestFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|max:255',
            'age' => 'numeric|min:18',
            'tags' => 'array|max:5',
            'status' => 'required|in:draft,published,archived',
        ];
    }
}
