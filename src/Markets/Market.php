<?php

namespace Glubschi\Seat\QuickSell\Markets;

/** A place to sell at: an NPC station or an Upwell structure. */
final class Market
{
    public const JITA_STATION = 60003760;

    public const THE_FORGE = 10000002;

    /** The five trade hubs, offered at the top of the market list. */
    public const HUBS = [60003760, 60008494, 60011866, 60004588, 60005686];

    public function __construct(
        public readonly string $kind,          // 'station' or 'structure'
        public readonly int $locationId,
        public readonly string $name,
        public readonly ?int $systemId,
        public readonly ?int $regionId,
    ) {
    }

    /** "station:60003760" or "structure:1035466617946", as used by the page. */
    public function id(): string
    {
        return $this->kind . ':' . $this->locationId;
    }

    public function isStructure(): bool
    {
        return $this->kind === 'structure';
    }

    public function isJita(): bool
    {
        return $this->locationId === self::JITA_STATION;
    }

    public function toArray(): array
    {
        return ['id' => $this->id(), 'kind' => $this->kind, 'name' => $this->name];
    }
}
