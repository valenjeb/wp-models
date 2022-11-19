<?php

declare(strict_types=1);

namespace Devly\WP\Models;

class Tag extends Term
{
    public static string $taxonomy = 'post_tag';
}
