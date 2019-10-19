<?php

namespace PiouPiou\RibsCronBundle\Controller;

use Cron\CronExpression;
use DateTime;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class RibsCronController extends AbstractController
{
    /**
     * @var ParameterBagInterface
     */
    protected $parameterBag;

    protected $crons;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    /**
     * @Route("/ribs-cron", name="ribs_cron")
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function cron(Request $request)
    {
        $ip = $request->server->get('REMOTE_ADDR');
        $allowed_ip_external = explode(", ", $this->getParameter("ribs_cron_ip_external"));

        if (in_array($ip, $allowed_ip_external) || $ip === $this->getParameter("ribs_cron_ip_internal")) {
            $this->crons = $this->getParameter("ribs_cron");
            $json_exec = $this->getCronFile();
            $now = new DateTime();

            // start executing crons
            foreach ($this->crons as $key => $cron) {
                if (!array_key_exists($key, $json_exec)) {
                    $this->addJsonEntry($key);
                    $json_exec = $this->getCronFile();
                }

                $next_exec = $json_exec[$key]["next_execution"];
                if (method_exists($this, $key)) {
                    if ($next_exec === null || in_array($ip, $allowed_ip_external)) {
                        $this->$key();
                    } else if ($now >= DateTime::createFromFormat("Y-m-d H:i:s", $next_exec)) {
                        $this->$key();
                    }

                    $cron = CronExpression::factory($this->getParameter("ribs_cron")[$key]);
                    $this->editJsonEntry($key, $cron->getNextRunDate()->format('Y-m-d H:i:s'));
                }
            }
        } else {
            throw new AccessDeniedHttpException("You haven't got access to this page");
        }

        return new Response();
    }

    /**
     * return the json file with all crons in it. If not exist, we create it add put cron like this :
     * key => nameOfMethodToExecute
     * [last_execution = null]
     * @return mixed|string
     */
    private function getCronFile()
    {
        $file = $this->getParameter("data_directory") . "cron/cron.json";

        if (!is_file($file)) {
            $this->createRecursiveDirFromRoot( $this->getParameter("data_directory").'cron');
            $fs = new Filesystem();
            $fs->touch($this->getParameter("data_directory") . "cron/cron.json");

            $crons = [];

            foreach ($this->crons as $key => $cron) {
                $crons[$key] = [
                    "next_execution" => null,
                ];
            }

            $fs->appendToFile($file, json_encode($crons));
        }

        $file = json_decode(file_get_contents($file), true);

        return $file;
    }

    /**
     * method that add new entry in config cron file
     * @param string $entry
     */
    private function addJsonEntry(string $entry)
    {
        $file = $this->getParameter("data_directory") . "cron/cron.json";
        $crons = json_decode(file_get_contents($file), true);

        $crons[$entry] = [
            "next_execution" => null,
        ];

        $this->writeJsonCron($crons);
    }

    /**
     * method to edit an entry in json
     * @param string $entry
     * @param string $next_execution
     */
    private function editJsonEntry(string $entry, string $next_execution)
    {
        $json = $this->getCronFile();

        if (array_key_exists($entry, $json)) {
            $json[$entry]["next_execution"] = $next_execution;

            $this->writeJsonCron($json);
        }
    }

    /**
     * method that writes the cron.json when we add or edit an entry
     * @param array $json
     */
    private function writeJsonCron(array $json)
    {
        $fs = new Filesystem();
        $file = $this->getParameter("data_directory") . "cron/cron.json";

        $fs->dumpFile($file, json_encode($json));
    }

    /**
     * method that create a tree of folders on each slash
     * @param $path
     * @return string
     */
    private function createRecursiveDirFromRoot($path)
    {
        $fs = new Filesystem();
        $root_dir = $this->parameterBag->get('kernel.project_dir') . "/";
        $new_path = $root_dir;
        $folders = explode("/", $path);

        foreach ($folders as $index => $folder) {
            $new_path .= $folder;

            if (!$fs->exists($new_path)) {
                $fs->mkdir($new_path);
            }

            if ($index + 1 < count($folders)) {
                $new_path .= "/";
            }
        }

        return $new_path;
    }
}
