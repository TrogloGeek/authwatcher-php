<?php

class sshd implements IFilter
{
    private $reports = array();

    public function init(array $config)
    {

    }

    public function processLine($line)
    {
        if (preg_match('/^(?<date>'.DATE_FORMAT.') \S+ sshd\[[0-9]+\]: Accepted (?<method>\S+) for (?<user>\S+) from (?<IP>\S+)/', $line, $matches)) {
            $this->reports[$matches['IP']][] = $matches['date'].': Logged onto user `'.$matches['user'].'` using `'.$matches['method'].'`';
        }
    }

    public function getIpReport()
    {
        return $this->reports;
    }
}
