<?php

namespace App\Enums;

enum SupplierProtocol: string
{
    case Api = 'api';
    case Csv = 'csv';
    case Json = 'json';
    case Xml = 'xml';
    case Sftp = 'sftp';
    case Manual = 'manual';
}
