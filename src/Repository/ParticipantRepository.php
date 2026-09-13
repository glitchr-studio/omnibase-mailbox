<?php

namespace Base\Mailbox\Repository;

use Base\Database\Repository\ServiceEntityRepository;
use Base\Mailbox\Entity\Participant;

/**
 * @method Participant|null find($id, $lockMode = null, $lockVersion = null)
 * @method Participant|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Participant[]    findAll()
 * @method Participant[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class ParticipantRepository extends ServiceEntityRepository
{
}
