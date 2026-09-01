<?php

declare(strict_types=1);

namespace OxidShipping\Module\Tariff;

use Doctrine\DBAL\ForwardCompatibility\Result;
use Doctrine\DBAL\Query\QueryBuilder;
use OxidEsales\EshopCommunity\Internal\Framework\Database\ConnectionFactoryInterface;
use OxidEsales\EshopCommunity\Internal\Framework\Database\Id;
use OxidEsales\EshopCommunity\Internal\Framework\Database\QueryBuilderFactoryInterface;
use OxidEsales\EshopCommunity\Internal\Transition\Utility\ContextInterface;
use OxidShipping\Engine\Input\TariffConfig;
use OxidShipping\Engine\Input\TariffDocument;

final class TariffRepository implements TariffRepositoryInterface
{
    private const TABLE = 'oxidshipping_tariff';

    private const JSON_FLAGS = JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function __construct(
        private QueryBuilderFactoryInterface $queryBuilderFactory,
        private ConnectionFactoryInterface $connectionFactory,
        private ContextInterface $context,
    ) {
    }

    public function findActive(): ?TariffConfig
    {
        $row = $this->fetchOne(
            $this->queryBuilderFactory->create()
                ->select('t.OXPAYLOAD')
                ->from(self::TABLE, 't')
                ->where('t.OXSHOPID = :shopId')
                ->andWhere('t.OXACTIVEFLAG = :active')
                ->setParameter('shopId', $this->shopId())
                ->setParameter('active', 1),
        );
        if ($row === null) {
            return null;
        }

        return TariffDocument::fromJson($this->stringField($row, 'OXPAYLOAD'));
    }

    public function listVersions(): array
    {
        $rows = $this->fetchAll(
            $this->queryBuilderFactory->create()
                ->select(
                    't.OXID',
                    't.OXVERSION',
                    't.OXHASH',
                    't.OXACTIVEFLAG',
                    't.OXAUTHORID',
                    't.OXTIMESTAMP',
                    't.OXPAYLOAD',
                )
                ->from(self::TABLE, 't')
                ->where('t.OXSHOPID = :shopId')
                ->setParameter('shopId', $this->shopId())
                ->orderBy('t.OXTIMESTAMP', 'DESC')
                ->addOrderBy('t.OXID', 'DESC'),
        );

        $versions = [];
        foreach ($rows as $row) {
            $authorId = $row['OXAUTHORID'] ?? null;
            $versions[] = new TariffVersion(
                $this->stringField($row, 'OXID'),
                $this->stringField($row, 'OXVERSION'),
                $this->stringField($row, 'OXHASH'),
                (int) ($row['OXACTIVEFLAG'] ?? 0) === 1,
                is_string($authorId) && $authorId !== '' ? $authorId : null,
                $this->stringField($row, 'OXTIMESTAMP'),
                $this->stringField($row, 'OXPAYLOAD'),
            );
        }

        return $versions;
    }

    public function saveVersion(TariffConfig $config, ?string $authorId): void
    {
        $payload = json_encode(TariffDocument::document($config), self::JSON_FLAGS);
        $shopId = $this->shopId();
        $connection = $this->connectionFactory->create();
        $connection->beginTransaction();
        try {
            // Unique (OXSHOPID, OXACTIVEFLAG) forbids a second 1; archive first, then insert.
            $this->archiveActive($shopId);
            $this->queryBuilderFactory->create()
                ->insert(self::TABLE)
                ->values([
                    'OXID' => ':id',
                    'OXSHOPID' => ':shopId',
                    'OXVERSION' => ':version',
                    'OXPAYLOAD' => ':payload',
                    'OXHASH' => ':hash',
                    'OXACTIVEFLAG' => ':active',
                    'OXAUTHORID' => ':authorId',
                ])
                ->setParameter('id', (string) Id::generate())
                ->setParameter('shopId', $shopId)
                ->setParameter('version', $config->version)
                ->setParameter('payload', $payload)
                ->setParameter('hash', TariffDocument::hash($config))
                ->setParameter('active', 1)
                ->setParameter('authorId', $authorId)
                ->execute();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function renameActive(TariffConfig $config): void
    {
        $affected = $this->queryBuilderFactory->create()
            ->update(self::TABLE)
            ->set('OXVERSION', ':version')
            ->set('OXPAYLOAD', ':payload')
            ->where('OXSHOPID = :shopId')
            ->andWhere('OXACTIVEFLAG = :active')
            ->setParameter('version', $config->version)
            ->setParameter('payload', json_encode(TariffDocument::document($config), self::JSON_FLAGS))
            ->setParameter('shopId', $this->shopId())
            ->setParameter('active', 1)
            ->execute();
        if (!is_numeric($affected) || (int) $affected !== 1) {
            throw new \RuntimeException('Shipping tariff version was not found for this shop.');
        }
    }

    public function activate(string $id): void
    {
        $shopId = $this->shopId();
        $connection = $this->connectionFactory->create();
        $connection->beginTransaction();
        try {
            $this->archiveActive($shopId);
            $affected = $this->queryBuilderFactory->create()
                ->update(self::TABLE)
                ->set('OXACTIVEFLAG', ':active')
                ->where('OXID = :id')
                ->andWhere('OXSHOPID = :shopId')
                ->setParameter('active', 1)
                ->setParameter('id', $id)
                ->setParameter('shopId', $shopId)
                ->execute();
            if (!is_numeric($affected) || (int) $affected !== 1) {
                throw new \RuntimeException('Shipping tariff version was not found for this shop.');
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    private function archiveActive(int $shopId): void
    {
        $this->queryBuilderFactory->create()
            ->update(self::TABLE)
            ->set('OXACTIVEFLAG', 'NULL')
            ->where('OXSHOPID = :shopId')
            ->andWhere('OXACTIVEFLAG = :active')
            ->setParameter('shopId', $shopId)
            ->setParameter('active', 1)
            ->execute();
    }

    private function shopId(): int
    {
        return $this->context->getCurrentShopId();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchOne(QueryBuilder $builder): ?array
    {
        $rows = $this->fetchAll($builder->setMaxResults(1));

        return $rows[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAll(QueryBuilder $builder): array
    {
        $result = $builder->execute();
        if (!$result instanceof Result) {
            throw new \RuntimeException('Shipping tariff query failed.');
        }

        $fetched = $result->fetchAllAssociative();

        /** @var list<array<string, mixed>> $fetched */
        return $fetched;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function stringField(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) && !is_numeric($value)) {
            throw new \InvalidArgumentException('Shipping tariff row is invalid.');
        }

        return (string) $value;
    }
}
