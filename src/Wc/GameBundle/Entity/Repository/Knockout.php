<?php

namespace Wc\GameBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Wc\GameBundle\Entity;

/**
 * KnockoutRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class Knockout extends EntityRepository
{

    public function getMatches()
    {
        $matches = array();
        foreach ($this->findAll() as &$knockout) {
            /** @var Entity\Knockout $knockout */
            if ($knockout->getFromgroup()) {
                list($number, $group) = str_split($knockout->getHometeam());
                $hometeam = $this->getFromGroup($number, $group);

                list($number, $group) = str_split($knockout->getAwayteam());
                $awayteam = $this->getFromGroup($number, $group);
            } else {
                $type = substr($knockout->getHometeam(), 0, 1);
                $id = substr($knockout->getHometeam(), 1);
                $hometeam = $this->getFromKnockout($matches, $type, $id);

                $type = substr($knockout->getAwayteam(), 0, 1);
                $id = substr($knockout->getAwayteam(), 1);
                $awayteam = $this->getFromKnockout($matches, $type, $id);
            }

            if ($hometeam !== null) {
                $knockout->setHometeam($hometeam);
            }

            if ($awayteam !== null) {
                $knockout->setAwayteam($awayteam);
            }

            $matches[$knockout->getMatchid()] = $knockout;

        }

        $out = array();
        foreach ($matches as $knockout) {
            if (array_key_exists($knockout->getStage()->getId(), $out)) {
                $out[$knockout->getStage()->getId()]['matches'][] = $knockout;
            } else {
                $out[$knockout->getStage()->getId()] = array(
                    'data' => $knockout->getStage(),
                    'matches' => array($knockout)
                );
            }
        }

        return $out;

    }

    private function getFromKnockout(array $matches, $type, $id)
    {
        if (! array_key_exists($id, $matches)) {
            return null;
        }

        /** @var Entity\Knockout $match */
        $match = $matches[$id];
        if ($match->getHomeresult() === null || $match->getAwayresult() === null) {
            return null;
        }

        switch(strtoupper($type)) {
            default:
            case 'W':
                return $match->getWinner();
            case 'L':
                return $match->getLoser();
        }
    }

    private function getFromGroup($number, $group)
    {
        $group = 'group_' . strtolower($group);
        switch($number) {
            case 1:
                return $this->getWinnerFromGroup($group);
            default:
            case 2:
                return $this->getSecondFromGroup($group);
        }
    }

    private function getWinnerFromGroup($group)
    {
        $groups = $this->getEntityManager()->getRepository('WcGameBundle:Stage')->getGroups($group);
        if ($groups[0]['complete']) {
            return $groups[0]['standings'][0]['team'];
        }
        return null;
    }

    private function getSecondFromGroup($group)
    {
        $groups = $this->getEntityManager()->getRepository('WcGameBundle:Stage')->getGroups($group);
        if ($groups[0]['complete']) {
            return $groups[0]['standings'][1]['team'];
        }
        return null;
    }

}
