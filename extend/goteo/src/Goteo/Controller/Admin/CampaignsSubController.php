<?php

namespace Goteo\Controller\Admin;

use Goteo\Library\Feed,
	Goteo\Application\Message,
    Goteo\Model;

class CampaignsSubController extends AbstractSubController {

    public function process ($action = 'list', $id = null, $filters = array(), $flag = null) {

        $status = Model\Call::status();

        $errors = array();

        // solo para nodos
        if ($this->isDefaultNode()) {
            Message::error('Non valid for default node!');
            return $this->redirect('/admin');
        }

        $node = $this->node;

        if ($this->isPost()) {

            // objeto
            $campaign = new Model\Campaign(array(
                'id' => $id,
                'node' => $node,
                'call' => $this->getPost('call'),
                'order' => $this->getPost('order'),
                'active' => $this->getPost('active')
            ));

			if ($campaign->save($errors)) {
                switch ($this->getPost('action')) {
                    case 'add':
                        Message::info('Convocatoria destacada correctamente');

                        // parece que no se usa
                        // $callData = Model\Call::getMini($this->getPost('call'));

                        break;
                    case 'edit':
                        Message::info('Destacado actualizado correctamente');
                        break;
                }
			}
			else {

                Message::error(implode(', ', $errors));

                // Convocatorias disponibles
                $calls = Model\campaign::available($campaign->call, $node);


                switch ($this->getPost('action')) {
                    case 'add':
                        return array(
                                'folder' => 'campaigns',
                                'file' => 'edit',
                                'action' => 'add',
                                'campaign' => $campaign,
                                'status' => $status,
                                'calls' => $calls
                        );
                        break;
                    case 'edit':
                        return array(
                                'folder' => 'campaigns',
                                'file' => 'edit',
                                'action' => 'edit',
                                'campaign' => $campaign,
                                'status' => $status,
                                'calls' => $calls
                        );
                        break;
                }
			}
		}

        switch ($action) {
            case 'active':
                $set = $flag == 'on' ? true : false;
                Model\Campaign::setActive($id, $set);
                return $this->redirect('/admin/campaigns');
                break;
            case 'up':
                Model\Campaign::up($id, $node);
                return $this->redirect('/admin/campaigns');
                break;
            case 'down':
                Model\Campaign::down($id, $node);
                return $this->redirect('/admin/campaigns');
                break;
            case 'remove':
                if (Model\Campaign::delete($id)) {
                    // ok
                } else {
                    Message::error('No se ha podido quitar la convocatoria');
                }
                return $this->redirect('/admin/campaigns');
                break;
            case 'add':
                // siguiente orden
                $next = Model\Campaign::next($node);

                // Convocatorias disponibles disponibles
                $calls = Model\Campaign::available(null, $node);
                if (empty($calls)) {
                    Message::info('No hay convocatorias disponibles para destacar');
                    return $this->redirect('/admin/campaigns');
                }

                return array(
                        'folder' => 'campaigns',
                        'file' => 'edit',
                        'action' => 'add',
                        'campaign' => (object) array('order' => $next, 'node'=>$node),
                        'status' => $status,
                        'calls' => $calls
                );
                break;
            case 'edit':
                $campaign = Model\Campaign::get($id);
                // Convocatorias disponibles
                $calls = Model\Campaign::available($campaign->call, $node);

                return array(
                        'folder' => 'campaigns',
                        'file' => 'edit',
                        'action' => 'edit',
                        'campaign' => $campaign,
                        'status' => $status,
                        'calls' => $calls
                );
                break;
        }


        $setted = Model\Campaign::getAll(false, $node);

        return array(
                'folder' => 'campaigns',
                'file' => 'list',
                'setted' => $setted
        );

    }

}

