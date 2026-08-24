<?php

declare(strict_types=1);

namespace Pam\Native\DevTools;

enum RecordKind: int
{
    case Event = 1;
    case StateSnapshot = 2;
    case Network = 3;
    case Performance = 4;
    case Error = 5;
    case Mutation = 6;
    case Frame = 7;
}
