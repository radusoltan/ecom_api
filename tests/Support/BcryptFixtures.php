<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * Pre-computed bcrypt hashes to avoid expensive password_hash() calls in unit tests.
 *
 * Each HASH_* constant is a valid bcrypt hash of the corresponding PLAIN_* constant.
 * Use password_verify(PLAIN_X, HASH_X) to confirm they match.
 */
final class BcryptFixtures
{
    public const HASH_AABBCCDD = '$2y$12$3mSRaQx/NkAlTzPRrenpBO9m44sn4/yop4QGeHbwvl2E8ikSW9ot.';
    public const PLAIN_AABBCCDD = 'AABBCCDD';

    public const HASH_EEFF0011 = '$2y$12$AN4viGfdmIbU16qsm3X2BeryulhvI6KOBZFHsxRlrZ5qeszpFyGaK';
    public const PLAIN_EEFF0011 = 'EEFF0011';

    public const HASH_XXXXXX00 = '$2y$12$d0bQmQZbydzkh5BD1Vgmd.UnCZLgeU028S7nZAE8doAlb7O7MPOHq';
    public const PLAIN_XXXXXX00 = 'XXXXXX00';

    public const HASH_ZZZZZZ99 = '$2y$12$Bz.FccXoMBVPfmWAe7VPNOr3TAHDp.vIZIObXbVJwAOHG.IyKlgWG';
    public const PLAIN_ZZZZZZ99 = 'ZZZZZZ99';

    public const HASH_NEWCODE1 = '$2y$12$7AoKtNQGWH3glN2G5sEMn.fI9Lw3jV2cyvCkvnzx.49QaU3GzO8Si';
    public const PLAIN_NEWCODE1 = 'NEWCODE1';

    public const HASH_NEWCODE2 = '$2y$12$4xOIUQX7eiFcwLHBsE3c5OE9FKRq3pGqGOXbB8aaLg0hmP1jpqK7S';
    public const PLAIN_NEWCODE2 = 'NEWCODE2';

    public const HASH_NEWCODE3 = '$2y$12$iLkdgNREYZERlzrI/ax1OO4/RdfFgqYM4TPOhWM4738K14KlWjVlq';
    public const PLAIN_NEWCODE3 = 'NEWCODE3';

    public const HASH_SOMECODE = '$2y$12$GdgcRq3dDTZNv1LFeLQf6eNQGkl2INZJGSARnwI7uj6SQ.ymZOt1e';
    public const PLAIN_SOMECODE = 'SOMECODE';

    public const HASH_SECOND00 = '$2y$12$EIDgg65VewJCj2ENGFXhoePBSehqobaMELphbKZsOl/wkveWQ3RUG';
    public const PLAIN_SECOND00 = 'SECOND00';

    public const HASH_SECRET = '$2y$12$4oE2rPVA9BRgMsAJxWkoOOXTMtnxn3Ux3jTbmKQYSP526mITuIMhm';
    public const PLAIN_SECRET = 'secret';

    public const HASH_FIRSTCOD = '$2y$12$0gBhKbwJLjT1q77cDTqHS.VFahlZ8yZoVvkliX7H/dZRsRvLYZVjK';
    public const PLAIN_FIRSTCOD = 'FIRSTCOD';
}
