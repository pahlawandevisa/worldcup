<?php

namespace Wc\GameBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Lexer;
use Wc\GameBundle\Entity;
use Wc\GameBundle\Data\TeamData;

/**
 * Stage
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class Stage extends EntityRepository
{

    /**
     * @param bool $group
     * @return Entity\Stage[]
     */
    public function getGroupMatches($group = false)
    {
        if (! $group) {
            return $this->findAll();
        }

        return $this->findBy(array('stage' => $group));
    }

    public function getGroupByDate()
    {
        $query = $this->getEntityManager()->getRepository('WcGameBundle:Game')->createQueryBuilder('g');
        $query
            //->select('g.*')
            //->join('Wc\GameBundle\Entity\Stage', 's', 'ON', 'g.stage_id = s.id')
            ->addOrderBy('g.matchdate')
            //->andWhere('s.isGroup = 1')
        ;

        #var_dump($query->getQuery()->getDQL());
        #exit;

        return $query->getQuery()->getResult();

    }

    /**
     * @param bool $group
     * @return array
     */
    public function getGroups($group = false)
    {
        $groups = array();
        $stages = $this->getGroupMatches($group);

        foreach($stages as $stage) {
            /** @var Entity\Stage $stage */
            if (! $stage->getIsGroup()) continue;

            // Loop matches and set vars for each stage
            $teams = array();
            $stagecomplete = true;
            foreach($stage->getGames() as $game) {
                /** @var Entity\Game $game */
                //$teams = &$groups[$stage->getId()]['teams'];
                if (!isset($teams[$game->getHometeam()->getId()])) {
                    $teams[$game->getHometeam()->getId()]['team'] = $game->getHometeam();
                    $teams[$game->getHometeam()->getId()]['data'] = new TeamData($game->getHometeam()->getId());
                }

                if (!isset($teams[$game->getAwayteam()->getId()])) {
                    $teams[$game->getAwayteam()->getId()]['team'] = $game->getAwayteam();
                    $teams[$game->getAwayteam()->getId()]['data'] = new TeamData($game->getAwayteam()->getId());
                }

                /** @var TeamData $ht */
                $ht = &$teams[$game->getHometeam()->getId()]['data'];
                /** @var TeamData $at */
                $at = &$teams[$game->getAwayteam()->getId()]['data'];

                if ($game->getAwayresult() === null) { // Match not played, continue
                    $stagecomplete = false;
                    continue;
                }

                // Played
                $ht->addPlayed();
                $at->addPlayed();
                // Goals
                $ht->addForGoals($game->getHomeresult());
                $at->addForGoals($game->getAwayresult());
                $ht->addAgainstGoals($game->getAwayresult());
                $at->addAgainstGoals($game->getHomeresult());

                if ($game->getAwayresult() > $game->getHomeresult()) { // Away win
                    $ht->addLoss();
                    $at->addWin();
                } elseif ($game->getHomeresult() > $game->getAwayresult()) { // Home win
                    $ht->addWin();
                    $at->addLoss();
                } else { // Draw
                    $ht->addDraw();
                    $at->addDraw();
                }
            }

            // Calculate stage standings
            $host = $this;
            $up = 1;
            $down = -1;
            $none = 0;
            usort($teams, function($ateam, $bteam) use ($host, $up, $down, $none) {
                /** @var TeamData $a */
                $a = $ateam['data'];
                /** @var TeamData $b */
                $b = $bteam['data'];

                /** @var Entity\Team $ateamobj */
                $ateamobj = $ateam['team'];
                /** @var Entity\Team $bteamobj */
                $bteamobj = $bteam['team'];

                if ($a->getPlayed() === 0 && $b->getPlayed() === 0) {
                    return strcmp($ateamobj->getName(), $bteamobj->getName());
                }

                $rules = array(
                    'Points', // A
                    'GoalsDiff', // B
                    'GoalsFor' // C
                );

                foreach ($rules as $rule) {
                    $rule = 'get' . $rule;
                    if ($a->$rule() < $b->$rule()) {
                        return $up;
                    }

                    if ($b->$rule() < $a->$rule()) {
                        return $down;
                    }
                }

                // Rule D+E
                /** @var Entity\Game $game */
                $game = $this->findMatch($ateamobj, $bteamobj);
                if ($game->getHometeam()->getId() == $ateamobj->getId()) {
                    if ($game->getHomeresult() < $game->getAwayresult()) {
                        return $up;
                    } elseif($game->getAwayresult() < $game->getHomeresult()) {
                        return $down;
                    }
                } else {
                    if ($game->getHomeresult() < $game->getAwayresult()) {
                        return $up;
                    } elseif($game->getAwayresult() < $game->getHomeresult()) {
                        return $down;
                    }
                }

                // Rule F
                if ($ateamobj->getOrder() > $bteamobj->getOrder()) {
                    return $down;
                } elseif ($ateamobj->getOrder() < $bteamobj->getOrder()) {
                    return $up;
                }

                return strcmp($ateamobj->getName(), $bteamobj->getName());

            });

            $groups[] = array(
                'complete' => $stagecomplete,
                'stage' => $stage,
                'standings' => $teams
            );
        }

        return $groups;

    }

    /**
     * @param Entity\Team $a
     * @param Entity\Team $b
     * @return Game
     * @throws \Doctrine\ORM\ORMException
     * @throws \InvalidArgumentException
     */
    private function findMatch(Entity\Team $a, Entity\Team $b)
    {
        $match = $this->getEntityManager()->getRepository('WcGameBundle:Game')->createQueryBuilder('g')
            ->andWhere('g.hometeam = :hometeam1 AND g.awayteam = :awayteam1')
            ->orWhere('g.hometeam = :hometeam2 AND g.awayteam = :awayteam2')
            ->setParameters(array(
                ':hometeam1' => $a->getId(),
                ':hometeam2' => $b->getId(),
                ':awayteam1' => $b->getId(),
                ':awayteam2' => $a->getId()
            ))
            ->getQuery()
            ->getResult()
        ;

        return $match[0];

    }


}
