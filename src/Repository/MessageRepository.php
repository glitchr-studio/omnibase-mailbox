<?php

namespace Base\Mailbox\Repository;

use App\Entity\User;
use Base\Database\Repository\ServiceEntityRepository;
use Base\Mailbox\Entity\Message;

/**
 * @method Message|null find($id, $lockMode = null, $lockVersion = null)
 * @method Message|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Message[]    findAll()
 * @method Message[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class MessageRepository extends ServiceEntityRepository
{
    /** The member's latest message, whatever the conversation - the flood check. */
    public function findLastBySender(User $sender): ?Message
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.sender = :sender')->setParameter('sender', $sender)
            ->orderBy('m.createdAt', 'DESC')->addOrderBy('m.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }
}
