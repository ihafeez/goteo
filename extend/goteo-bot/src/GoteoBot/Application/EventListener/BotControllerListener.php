<?php

namespace GoteoBot\Application\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

use Goteo\Model\Project;
use Goteo\Model\Project\ProjectMilestone;
use Goteo\Model\Event;
use Goteo\Model\Milestone;
use Goteo\Model\Image;
use Goteo\Application\AppEvents;
use Goteo\Console\ConsoleEvents;
use Goteo\Application\Event\FilterInvestRequestEvent;

use GoteoBot\Model\ProjectBot;
use GoteoBot\Model\Bot\TelegramBot;
use GoteoBot\Controller\BotProjectDashboardController;

use Goteo\Application\Exception\DuplicatedEventException;

class BotControllerListener implements EventSubscriberInterface
{


    public function onController(FilterControllerEvent $event) {
        $request = $event->getRequest();
        $controller = $request->attributes->get('_controller');
        if(!is_string($controller)) return;

        if( strpos($controller, 'Goteo\Controller\Dashboard\ProjectDashboardController') !== false ||
            strpos($controller, 'Goteo\Controller\Dashboard\TranslateProjectDashboardController') !== false ||
            strpos($controller, 'Goteo\Controller\Dashboard\SettingsDashboardController::profileAction') !== false ||
            $controller === 'Goteo\Controller\ProjectExtrasController::indexAction' ||
            strpos($controller, 'Goteo\Controller\ProjectController') !== false ||
            strpos($controller, 'Goteo\Controller\ProjectExtrasController') !== false ) {

            $pid = $request->attributes->get('pid');
            if(!$pid) return;
            BotProjectDashboardController::createBotSidebar(Project::get($pid));

        }

    }

    private function create_milestone($project, $type){
        //Insert milestone
        $project_milestone= new ProjectMilestone();
        $project_milestone->project=$project->id;
        $project_milestone->milestone_type=$type;
        $project_milestone->source_message='bot_message';


        try {
            $action = [$project->id, 'milestone-day-bot', $type];
            $event = new Event($action, 'milestone');
        } catch(DuplicatedEventException $e) {
            // $this->warning('Duplicated event', ['action' => $e->getMessage(), $project, 'event' => "milestone:$type"]);
            return;
        }

        $event->fire(function() use ($project_milestone) {
            $project_milestone->save($errors);
        });

        $projectBot = ProjectBot::get($project->id);
        if ($projectBot) {
            if ($projectBot->platform == ProjectBot::TELEGRAM) {
                $bot = new TelegramBot();
                $bot->createBot();
                $milestone = Milestone::get($project_milestone->milestone, $project->lang);
                if ($milestone->image) {
                    $image = Image::get($milestone->image);
                    if ($image->getType() == "gif") {
                        $bot->sendAnimation($projectBot->channel_id, $image, $milestone->bot_message);
                    }
                    else {
                        $bot->sendImage($projectBot->channel_id, $image, $milestone->bot_message);
                    }
                } else {
                    $bot->sendMessage($projectBot->channel_id, $milestone->bot_message);
                }
            }
        }
    }

    /**
     * Insert milestone depending on day
     * @param  FilterProjectEvent $event
     */
    public function onProjectActive(FilterProjectEvent $event) {
        $project = $event->getProject();
        $type = 'day-'.$event->getDays();

        $this->create_milestone($project, $type);
    }


    /**
     * Sends a reminder to the owners that they have to accomplish with the collective returns
     * @param  FilterProjectEvent $event
     */
    public function onInvestSucceeded(FilterInvestRequestEvent $event) {

        $method   = $event->getMethod();
        $response = $event->getResponse();
        $invest   = $method->getInvest();
        $project  = $invest->getProject();


        //Milestones by percentage
        $percentage = $project->mincost ? ($project->invested / $project->mincost) * 100 : 0;

        if($percentage>=15&&$percentage<20)
            $type='15-percent-reached';
        elseif($percentage>=20&&$percentage<40)
            $type='20-percent-reached';
        elseif($percentage>=40&&$percentage<50)
            $type='40-percent-reached';
        elseif($percentage>=50&&$percentage<70)
            $type='50-percent-reached';
        elseif($percentage>=70&&$percentage<80)
            $type='70-percent-reached';
        elseif($percentage>=80)
            $type='80-percent-reached';

        if($type)
            $this->create_milestone($project, $type);
    }


    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::CONTROLLER => 'onController',
            AppEvents::INVEST_SUCCEEDED    => array('onInvestSucceeded', 100),
            ConsoleEvents::PROJECT_WATCH    => 'onProjectActive'
        );
    }
}

