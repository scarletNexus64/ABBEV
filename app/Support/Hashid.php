<?php

namespace App\Support;

/**
 * Encodage RÉVERSIBLE d'identifiants entiers vers une chaîne courte non
 * séquentielle (façon YouTube), sans librairie externe ni migration.
 *
 * Basé sur une transformation de type « Optimus » (multiplication par un grand
 * premier modulo 2^31, inversible via l'inverse modulaire) puis encodage base62.
 * Le résultat est PRÉFIXÉ par une lettre fixe pour le distinguer sans ambiguïté
 * d'un id brut décimal : cela permet aux routes web d'utiliser l'id encodé
 * tandis que l'API mobile continue d'envoyer des ids bruts (repli sûr).
 *
 * NB : ce n'est pas du chiffrement — c'est de l'obfuscation (empêche le i++
 * trivial). La vraie protection du contenu repose sur l'auth + l'abonnement +
 * le throttle + les URLs signées (cf. LocalVideoStreamController / Bunny).
 */
class Hashid
{
    // Constantes Optimus valides : PRIME * INVERSE ≡ 1 (mod 2^31).
    private const PRIME   = 1580030173;
    private const INVERSE = 59260789;
    private const RANDOM  = 1163945558;
    private const MAX     = 2147483647; // 2^31 - 1

    private const PREFIX   = 'k';
    private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public static function encode(int $id): string
    {
        $n = (($id * self::PRIME) & self::MAX) ^ self::RANDOM;

        return self::PREFIX . self::toBase62($n);
    }

    /** Retourne l'id d'origine, ou null si la chaîne n'est pas un id encodé valide. */
    public static function decode(string $hash): ?int
    {
        if ($hash === '' || $hash[0] !== self::PREFIX) {
            return null;
        }

        $body = substr($hash, 1);
        if ($body === '' || strspn($body, self::ALPHABET) !== strlen($body)) {
            return null;
        }

        $n  = self::fromBase62($body);
        $id = (($n ^ self::RANDOM) * self::INVERSE) & self::MAX;

        return $id;
    }

    private static function toBase62(int $n): string
    {
        if ($n === 0) {
            return '0';
        }

        $out = '';
        while ($n > 0) {
            $out = self::ALPHABET[$n % 62] . $out;
            $n = intdiv($n, 62);
        }

        return $out;
    }

    private static function fromBase62(string $s): int
    {
        $n = 0;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $n = $n * 62 + strpos(self::ALPHABET, $s[$i]);
        }

        return $n;
    }
}
