<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agent\ListEmailsRequest;
use App\Http\Requests\Agent\UpdateEmailRequest;
use App\Models\Email;
use Illuminate\Http\JsonResponse;

class EmailController extends Controller
{
    public function index(ListEmailsRequest $request): JsonResponse
    {
        $emails = Email::query()
            ->when($request->validated('processed') !== null, fn ($query) => $query->where('processed', $request->boolean('processed')))
            ->latest('received_at')
            ->paginate($request->validated('per_page') ?? 50);

        return response()->json($emails);
    }

    public function update(UpdateEmailRequest $request, Email $email): JsonResponse
    {
        $email->update($request->payload());

        return response()->json($email->refresh());
    }
}
