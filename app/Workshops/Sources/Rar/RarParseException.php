<?php

namespace App\Workshops\Sources\Rar;

use RuntimeException;

/** A stored authorisation that cannot be turned into a workshop; the record keeps the reason. */
class RarParseException extends RuntimeException {}
