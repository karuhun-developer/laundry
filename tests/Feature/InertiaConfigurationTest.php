<?php

it('keeps Inertia server-side rendering disabled', function (): void {
    expect(config('inertia.ssr.enabled'))->toBeFalse();
});
