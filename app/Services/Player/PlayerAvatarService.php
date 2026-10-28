<?php

namespace App\Services\Player;

use App\Models\Player\Player;
use App\Models\Users\User;
use App\Repositories\Player\PlayerRepository;
use App\Support\Media\SquareJpeg;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PlayerAvatarService
{
    public const SIZE = SquareJpeg::SIZE;

    public function __construct(
        private PlayerRepository $playerRepository,
    ) {}

    public function storeForOwner(Player $player, User $actor, UploadedFile $file): Player
    {
        $this->assertOwner($player, $actor);
        $this->validateUpload($file);

        $binary = SquareJpeg::encode($file, 'avatar');
        $path = 'avatars/'.$player->id.'.jpg';
        $previous = $player->avatar_path;

        Storage::disk('public')->put($path, $binary);

        if (is_string($previous) && $previous !== '' && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return $this->playerRepository->updateAvatarPath($player, $path);
    }

    public function removeForOwner(Player $player, User $actor): Player
    {
        $this->assertOwner($player, $actor);

        if (is_string($player->avatar_path) && $player->avatar_path !== '') {
            Storage::disk('public')->delete($player->avatar_path);
        }

        return $this->playerRepository->updateAvatarPath($player, null);
    }

    public function validateUpload(UploadedFile $file): void
    {
        SquareJpeg::assertImage($file, 'avatar');
    }

    private function assertOwner(Player $player, User $actor): void
    {
        $actorPlayer = $actor->player;
        if ($actorPlayer === null || (int) $actorPlayer->id !== (int) $player->id) {
            abort(403, 'Możesz edytować tylko swój profil.');
        }

        if (! $player->user_id || $player->is_bye) {
            abort(404, 'Profil dostępny tylko dla graczy zarejestrowanych.');
        }
    }
}
