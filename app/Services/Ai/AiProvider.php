<?php
namespace App\Services\Ai;
use App\Models\User;
interface AiProvider {
    public function models(User $user): array;
    public function generate(User $user, string $instruction, array $payload, array $schema, array $parts = []): array;
}
