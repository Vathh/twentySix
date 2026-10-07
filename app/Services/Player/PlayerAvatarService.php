<?php

namespace App\Services\Player;

use App\Models\Player\Player;
use App\Models\Users\User;
use App\Repositories\Player\PlayerRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PlayerAvatarService
{
    public const SIZE = 512;

    public function __construct(
        private PlayerRepository $playerRepository,
    ) {}

    public function storeForOwner(Player $player, User $actor, UploadedFile $file): Player
    {
        $this->assertOwner($player, $actor);
        $this->validateUpload($file);

        $binary = $this->renderSquareJpeg($file);
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
        validator(
            ['avatar' => $file],
            ['avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048']],
            [],
            ['avatar' => 'zdjęcie'],
        )->validate();
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

    private function renderSquareJpeg(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());
        $src = $contents !== false ? @imagecreatefromstring($contents) : false;

        if ($src === false) {
            throw ValidationException::withMessages([
                'avatar' => 'Nie udało się odczytać obrazu.',
            ]);
        }

        $width = imagesx($src);
        $height = imagesy($src);
        if ($width < 1 || $height < 1) {
            imagedestroy($src);
            throw ValidationException::withMessages([
                'avatar' => 'Nie udało się odczytać obrazu.',
            ]);
        }

        $side = min($width, $height);
        $srcX = (int) floor(($width - $side) / 2);
        $srcY = (int) floor(($height - $side) / 2);

        $dst = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, self::SIZE, self::SIZE, $side, $side);
        imagedestroy($src);

        ob_start();
        $written = imagejpeg($dst, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($dst);

        if ($written !== true || ! is_string($jpeg) || $jpeg === '') {
            throw ValidationException::withMessages([
                'avatar' => 'Nie udało się zapisać obrazu.',
            ]);
        }

        return $jpeg;
    }
}
