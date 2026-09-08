<?php

namespace App\Enums;

/**
 * How an offer's numbers reached us. A price read from a nightly CSV deserves less
 * trust at checkout than one confirmed by a realtime API call a minute ago.
 */
enum OfferSourceType: string
{
    case Api = 'api';
    case Sftp = 'sftp';
    case Ftp = 'ftp';
    case Csv = 'csv';
    case Xml = 'xml';
    case Json = 'json';
    case Portal = 'portal';
    case Manual = 'manual';
}
