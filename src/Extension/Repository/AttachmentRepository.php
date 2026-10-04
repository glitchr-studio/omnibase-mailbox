<?php

namespace Base\Mailbox\Extension\Repository;

use Base\Mailbox\Entity\Conversation;
use Base\Mailbox\Extension\Entity\Attachment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Attachment> */
class AttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attachment::class);
    }

    /** @return array<int, list<Attachment>> a conversation's attachments, by message id */
    public function findByConversation(Conversation $conversation): array
    {
        $byMessage = [];
        foreach ($this->createQueryBuilder('a')->innerJoin('a.message', 'm')->andWhere('m.conversation = :c')->setParameter('c', $conversation)->orderBy('a.id', 'ASC')->getQuery()->getResult() as $attachment) {
            $byMessage[$attachment->getMessage()->getId()][] = $attachment;
        }

        return $byMessage;
    }
}
