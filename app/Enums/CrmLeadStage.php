<?php

declare(strict_types=1);

namespace App\Enums;

enum CrmLeadStage: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';
}
