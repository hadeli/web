<?php


namespace AppBundle\Model\Repository;


use AppBundle\Model\Event;
use AppBundle\Model\GithubUser;
use AppBundle\Model\Speaker;
use AppBundle\Model\Talk;
use CCMBenchmark\Ting\Driver\Mysqli\Serializer\Boolean;
use CCMBenchmark\Ting\Query\QueryException;
use CCMBenchmark\Ting\Repository\HydratorSingleObject;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\MetadataInitializer;
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;

class TalkRepository extends Repository implements MetadataInitializer
{
    public function saveWithSpeakers(Talk $talk, array $speaker)
    {
        try {
            $this->startTransaction();
            $this->unitOfWork->pushSave($talk);
            $this->unitOfWork->process();
            $query = $this->getPreparedQuery(
                'INSERT INTO afup_conferenciers_sessions (session_id, conferencier_id) VALUES (LAST_INSERT_ID(), :speaker);'
            )->setParams(['speaker' => $speaker->getId()]);
            $query->execute();
            $this->commit();
        } catch (QueryException $exception) {
            $this->rollback();
            throw $exception;
        }
    }

    public function addSpeaker(Speaker $speaker, Talk $talk)
    {

    }

    /**
     * @param Event $event
     * @param Speaker $speaker
     * @return \CCMBenchmark\Ting\Repository\CollectionInterface
     */
    public function getTalksBySpeaker(Event $event, Speaker $speaker)
    {
        $query = $this->getPreparedQuery(
            'SELECT sessions.session_id, titre, abstract, id_forum
            FROM afup_sessions sessions
            LEFT JOIN afup_conferenciers_sessions cs ON cs.session_id = sessions.session_id
            WHERE id_forum = :event AND cs.conferencier_id = :speaker
            ORDER BY titre
            LIMIT 0, 10
            '
        )->setParams(['event' => $event->getId(), 'speaker' => $speaker->getId()]);

        return $query->query($this->getCollection(new HydratorSingleObject()));
    }

    /**
     * Retrieve the list of talks to rate
     *
     * @param Event $event
     * @param GithubUser $user
     * @param int $limit
     * @return \CCMBenchmark\Ting\Repository\CollectionInterface
     */
    public function getTalksNotRatedByUser(Event $event, GithubUser $user, $limit = 10)
    {
        $query = $this->getPreparedQuery(
            'SELECT sessions.session_id, titre, abstract, id_forum, asvg.id, asvg.comment, asvg.vote
            FROM afup_sessions sessions
            LEFT JOIN afup_sessions_vote_github asvg ON (asvg.session_id = sessions.session_id AND asvg.user = :user)
            WHERE plannifie = 0 AND id_forum = :event
            ORDER BY RAND()
            LIMIT 0, 10
            '
        )->setParams(['event' => $event->getId(), 'user' => $user->getId()]);

        return $query->query();
    }

    public function getNewTalksToRate(Event $event, GithubUser $user, $limit = 10)
    {
        $query = $this->getPreparedQuery(
            'SELECT sessions.session_id, titre, abstract, id_forum
            FROM afup_sessions sessions
            LEFT JOIN afup_sessions_vote_github asvg ON (asvg.session_id = sessions.session_id AND asvg.user = :user)
            WHERE plannifie = 0 AND id_forum = :event
            AND asvg.id IS NULL
            ORDER BY RAND()
            LIMIT 0, 10
            '
        )->setParams(['event' => $event->getId(), 'user' => $user->getId()]);

        return $query->query();
    }

    /**
     * @param SerializerFactoryInterface $serializerFactory
     * @param array $options
     * @return Metadata
     */
    public static function initMetadata(SerializerFactoryInterface $serializerFactory, array $options = [])
    {
        $metadata = new Metadata($serializerFactory);
        $metadata->setEntity(Talk::class);
        $metadata->setConnectionName('main');
        $metadata->setDatabase($options['database']);
        $metadata->setTable('afup_sessions');

        $metadata
            ->addField([
                'columnName' => 'session_id',
                'fieldName' => 'id',
                'primary'       => true,
                'autoincrement' => true,
                'type' => 'int'
            ])
            ->addField([
                'columnName' => 'id_forum',
                'fieldName' => 'forumId',
                'type' => 'int'
            ])
            ->addField([
                'columnName' => 'date_soumission',
                'fieldName' => 'submittedOn',
                'type' => 'datetime',
                'serializer_options' => [
                    'unserialize' => ['unSerializeUseFormat' => false]
                ]
            ])
            ->addField([
                'columnName' => 'titre',
                'fieldName' => 'title',
                'type' => 'string'
            ])
            ->addField([
                'columnName' => 'abstract',
                'fieldName' => 'abstract',
                'type' => 'string'
            ])
            ->addField([
                'columnName' => 'genre',
                'fieldName' => 'type',
                'type' => 'int'
            ])
            ->addField([
                'columnName' => 'skill',
                'fieldName' => 'skill',
                'type' => 'int'
            ])
            ->addField([
                'columnName' => 'plannifie',
                'fieldName' => 'scheduled',
                'type' => 'bool',
                'serializer' => Boolean::class
            ])
        ;

        return $metadata;
    }

}
