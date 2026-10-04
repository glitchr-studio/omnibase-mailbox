<?php

namespace Base\Mailbox\Extension\Repository;

use Base\Mailbox\Entity\Conversation;
use Base\Mailbox\Extension\Entity\DeskConversation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DeskConversation> */
class DeskConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeskConversation::class);
    }

    public function findOneByConversation(Conversation $conversation): ?DeskConversation
    {
        return $this->findOneBy(['conversation' => $conversation]);
    }

    /** @param list<string> $desks the conversations written to those desks, the latest first */
    public function createDeskQuery(array $desks): Query
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('c')->from(Conversation::class, 'c')
            ->innerJoin(DeskConversation::class, 'd', 'WITH', 'd.conversation = c')
            ->leftJoin('c.lastSender', 'ls')->addSelect('ls')
            ->andWhere('d.desk IN (:desks)')->setParameter('desks', $desks ?: [''])
            ->orderBy('c.lastMessageAt', 'DESC')
            ->getQuery();
    }

    /** @param list<string> $desks how many wait for an answer: their author wrote last */
    public function countWaiting(array $desks): int
    {
        if ([] === $desks) {
            return 0;
        }

        return (int) $this->createQueryBuilder('d')->select('COUNT(d.id)')
            ->innerJoin('d.conversation', 'c')
            ->andWhere('d.desk IN (:desks)')->setParameter('desks', $desks)
            ->andWhere('c.lastSender = d.author')
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * @param list<string> $desks
     *
     * @return list<DeskConversation>
     */
    public function findWaiting(array $desks, int $limit = 5): array
    {
        if ([] === $desks) {
            return [];
        }

        return $this->createQueryBuilder('d')->addSelect('c')
            ->innerJoin('d.conversation', 'c')
            ->andWhere('d.desk IN (:desks)')->setParameter('desks', $desks)
            ->andWhere('c.lastSender = d.author')
            ->orderBy('c.lastMessageAt', 'ASC')->setMaxResults($limit)
            ->getQuery()->getResult();
    }
}
