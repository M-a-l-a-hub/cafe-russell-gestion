<?php

declare(strict_types=1);

/**
 * L'utilisateur est connu mais n'a pas le droit d'effectuer l'opération.
 * Hérite de RuntimeException : le code existant qui attrape déjà
 * RuntimeException continue de fonctionner.
 */
class AccesRefuseException extends RuntimeException
{
}
