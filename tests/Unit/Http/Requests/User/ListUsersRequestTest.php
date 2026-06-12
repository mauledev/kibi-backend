<?php

use App\Http\Requests\User\ListUsersRequest;

/**
 * Build a ListUsersRequest with the given filter[role] value.
 *
 * @param  string|array<int, string>|null  $role
 */
function makeListUsersRequest(string|array|null $role): ListUsersRequest
{
    $request = new ListUsersRequest;

    if ($role !== null) {
        $request->merge(['filter' => ['role' => $role]]);
    }

    return $request;
}

describe('ListUsersRequest::wantsUnassigned', function () {
    it('is true when filter[role] is the string "none"', function () {
        expect(makeListUsersRequest('none')->wantsUnassigned())->toBeTrue();
    });

    it('is true when filter[role] is ["none"] (single-element array)', function () {
        expect(makeListUsersRequest(['none'])->wantsUnassigned())->toBeTrue();
    });

    it('is false for a concrete slug', function () {
        expect(makeListUsersRequest('director')->wantsUnassigned())->toBeFalse();
    });

    it('is false when "none" is mixed with other slugs', function () {
        expect(makeListUsersRequest(['none', 'director'])->wantsUnassigned())->toBeFalse();
    });

    it('is false when filter[role] is absent', function () {
        expect(makeListUsersRequest(null)->wantsUnassigned())->toBeFalse();
    });
});

describe('ListUsersRequest::roleSlugs', function () {
    it('returns an empty array for the unassigned sentinel', function () {
        expect(makeListUsersRequest('none')->roleSlugs())->toBe([]);
    });

    it('wraps a single slug string into an array', function () {
        expect(makeListUsersRequest('director')->roleSlugs())->toBe(['director']);
    });

    it('passes through an array of slugs', function () {
        expect(makeListUsersRequest(['director', 'teacher'])->roleSlugs())->toBe(['director', 'teacher']);
    });

    it('returns an empty array when absent', function () {
        expect(makeListUsersRequest(null)->roleSlugs())->toBe([]);
    });
});
