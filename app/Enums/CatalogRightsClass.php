<?php

namespace App\Enums;

enum CatalogRightsClass: string
{
    case OpenRedistributable = 'open_redistributable';
    case PublicInformation = 'public_information';
    case PermissionedRedistributable = 'permissioned_redistributable';
    case CommerceOnly = 'commerce_only';
    case InternalReference = 'internal_reference';
    case Restricted = 'restricted';
    case UnknownPendingReview = 'unknown_pending_review';
}
