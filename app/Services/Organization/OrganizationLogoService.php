<?php

namespace App\Services\Organization;

use App\Models\Organization\Organization;
use App\Repositories\Organization\OrganizationRepository;
use App\Support\Media\SquareJpeg;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OrganizationLogoService
{
    public function __construct(
        private OrganizationRepository $organizations,
    ) {}

    public function store(Organization $organization, UploadedFile $file): Organization
    {
        SquareJpeg::assertImage($file, 'logo');
        $binary = SquareJpeg::encode($file, 'logo');
        $path = 'organization-logos/'.$organization->id.'.jpg';
        $previous = $organization->logo_path;

        Storage::disk('public')->put($path, $binary);

        if (is_string($previous) && $previous !== '' && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return $this->organizations->updateLogoPath($organization, $path);
    }

    public function remove(Organization $organization): Organization
    {
        if (is_string($organization->logo_path) && $organization->logo_path !== '') {
            Storage::disk('public')->delete($organization->logo_path);
        }

        return $this->organizations->updateLogoPath($organization, null);
    }
}
