<?php

namespace Tests\Unit\Support;

use App\Support\MobileApkDownloadUrl;
use PHPUnit\Framework\TestCase;

class MobileApkDownloadUrlTest extends TestCase
{
    public function test_hosted_apk_drops_the_old_host(): void
    {
        $href = MobileApkDownloadUrl::href(
            'https://dartscore.studiokam.pl/downloads/twentysix-1.0.23.apk',
        );

        $this->assertSame('/downloads/twentysix-1.0.23.apk', $href);
    }

    public function test_external_build_page_stays_absolute(): void
    {
        $url = 'https://expo.dev/accounts/vathh/projects/twentysix/builds/abc';

        $this->assertSame($url, MobileApkDownloadUrl::href($url));
    }
}
