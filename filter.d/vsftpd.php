<?php

class vsftpd implements IFilter
{
    private $reports = array();

    public function init(array $config)
    {

    }

    public function processLine($line)
    {
        if (preg_match('/^(?<date>'.DATE_FORMAT.').*\FTP command: Client "(?<IP>\S+)", "USER (?<user>\S+)"/', $line, $matches)) {
            $this->reports[$matches['IP']][] = $matches['date'].': Trial to log onto user `'.$matches['user'].'`';
        }
        if (preg_match('/^(?<date>'.DATE_FORMAT.').*\[(?<user>\S+)\] OK LOGIN: Client "(?<IP>\S+)"/', $line, $matches)) {
            $this->reports[$matches['IP']][] = $matches['date'].': Logged onto user `'.$matches['user'].'`';
        }
    }

    public function getIpReport()
    {
        return $this->reports;
    }
}
