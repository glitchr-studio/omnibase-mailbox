<?php

namespace Base\Mailbox\Repository;

use App\Entity\User;
use Base\Database\Repository\ServiceEntityRepository;
use Base\Mailbox\Entity\Conversation;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;

/**
 * @method Conversation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Conversation|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Conversation[]    findAll()
 * @method Conversation[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class ConversationRepository extends ServiceEntityRepository
{
    public const BOX_INBOX = 'inbox';
    public const BOX_ARCHIVE = 'archive';
    public const BOX_SENT = 'sent';

    /**
     * A member's box: their participation row, not deleted, archived or not.
     *
     * The join on the member's own participation is for filtering only:
     * fetch-joining the collections here would break the paginator's count
     * query (it groups by the root id), and a page of twenty conversations
     * loads its participants lazily at no visible cost.
     */
    private function createBoxQueryBuilder(User $user, string $box): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'me', 'WITH', 'me.user = :user')
            ->leftJoin('c.lastSender', 'ls')->addSelect('ls')
            ->andWhere('me.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('c.lastMessageAt', \SortDirection::Descending);

        switch ($box) {
            case self::BOX_ARCHIVE:
                $qb->andWhere('me.archivedAt IS NOT NULL');
                break;
            case self::BOX_SENT:
                $qb->andWhere('me.archivedAt IS NULL')->andWhere('c.lastSender = :user');
                break;
            default:
                $qb->andWhere('me.archivedAt IS NULL');
        }

        return $qb;
    }

    public function createBoxQuery(User $user, string $box = self::BOX_INBOX): Query
    {
        return $this->createBoxQueryBuilder($user, $box)->getQuery();
    }

    /** How many conversations sit in the member's inbox (the quota). */
    public function countInbox(User $user): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->innerJoin('c.participants', 'me', 'WITH', 'me.user = :user')
            ->andWhere('me.deletedAt IS NULL')->andWhere('me.archivedAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();
    }

    /** Conversations with something the member has not read yet. */
    public function countUnread(User $user): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->innerJoin('c.participants', 'me', 'WITH', 'me.user = :user')
            ->andWhere('me.deletedAt IS NULL')
            ->andWhere('c.lastSender IS NULL OR c.lastSender != :user')
            ->andWhere('me.lastReadAt IS NULL OR me.lastReadAt < c.lastMessageAt')
            ->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();
    }

    /** A conversation the member takes part in (and has not deleted), fully loaded. */
    public function findOneForUser(int $id, User $user): ?Conversation
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.participants', 'me', 'WITH', 'me.user = :user')
            ->leftJoin('c.participants', 'p')->addSelect('p')
            ->leftJoin('p.user', 'pu')->addSelect('pu')
            ->leftJoin('c.messages', 'm')->addSelect('m')
            ->leftJoin('m.sender', 's')->addSelect('s')
            ->andWhere('c.id = :id')->setParameter('id', $id)
            ->andWhere('me.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()->getOneOrNullResult();
    }
}
