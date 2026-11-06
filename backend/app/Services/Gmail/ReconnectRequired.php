<?php

namespace App\Services\Gmail;

/**
 * Google no longer accepts the stored refresh token, so the account has to be connected again.
 */
class ReconnectRequired extends GmailException
{
}
