<?php

namespace Goteo\Controller\Admin;

use Goteo\Library\Text,
    Goteo\Application\Message,
	Goteo\Application\Session,
	Goteo\Library\Feed,
    Goteo\Model;

class CallsSubController extends AbstractSubController {

    /**
     * @param action: (review | open | publish | cancel | enable | complete | delete)
     */
    public function process ($action = 'list', $id = null, $filters = array()) {

        $errors = array();
        $user = Session::getUser();

        $log_text = null;

        /*
         * switch action,
         * proceso que sea,
         * redirect
         *
         */
        $call = Model\Call::get($id);


        // si es admin (no superadmin) si no la tiene asignada no puede hacer otra cosa que no sea listar.
        if ($action != 'list'
            && isset($user->roles['admin'])
            && !isset($user->roles['superadmin']) // no es superadmin
            ) {
                // Si no la tiene asignada no puede gestionar cosas
                if (isset($id) && !$call->isAdmin($user->id)) {
                    Message::error('No tienes permiso para gestionar esta convocatoria');
                    return $this->redirect("/admin/calls");
                } elseif (!isset($id)) {
                    // si no hay id es crear y tampoco puede
                    Message::error('No tienes permiso para gestionar convocatorias');
                    return $this->redirect("/admin/calls");
                }
        }

        switch ($action) {
            case 'review': // listo para aplicar proyectos (se publica, sino que siga en edicion)
                if ($call->ready($errors)) {
                    $log_text = 'El admin %s ha pasado la convocatoria %s a <span class="red">Revisión</span>';
                } else {
                    $log_text = 'Al admin %s le ha fallado al pasar la convocatoria %s a <span class="red">Revisión</span>';
                }
                break;
            case 'open': // comienza la campaña de postulacion
                if ($call->open($errors)) {
                    $log_text = 'El admin %s ha pasado la convocatoria %s al estado <span class="red">Recepción de proyectos</span>';
                } else {
                    $log_text = 'Al admin %s le ha fallado al pasar la convocatoria %s al estado <span class="red">Recepción de proyectos</span>';
                }
                break;
            case 'publish': // comienza la campaña de pasta
                if ($call->publish($errors)) {
                    $log_text = 'El admin %s ha pasado la convocatoria %s al estado <span class="red">en Campaña</span>';
                } else {
                    $log_text = 'Al admin %s le ha fallado al pasar la convocatoria %s al estado <span class="red">en Campaña</span>';
                }
                break;
            case 'cancel': // caducar una campaña o aplicacion antes de hora
                if ($call->fail($errors)) {
                    $log_text = 'El admin %s ha pasado la convocatoria %s al estado <span class="red">Caducado</span>';
                } else {
                    $log_text = 'Al admin %s le ha fallado al pasar la convocatoria %s al estado <span class="red">Caducado</span>';
                }
                break;
            case 'enable': // reabrir la edición Ojo que se quita de campña! Se puede editar mientras está en campaña?
                if ($call->enable($errors)) {
                    $log_text = 'El admin %s ha pasado la convocatoria %s al estado <span class="red">Edición</span>';
                } else {
                    $log_text = 'Al admin %s le ha fallado al pasar la convocatoria %s al estado <span class="red">Edición</span>';
                }
                break;
            case 'complete': // dar por finalizada la convocatoria
                if ($call->succeed($errors)) {
                    $log_text = 'El admin %s ha marcado la convocatoria %s como <span class="red">Finalizada</span>';
                } else {
                    $log_text = 'Al admin %s le ha fallado al marcar la convocatoria %s como <span class="red">Finalizada</span>';
                }
                break;
            case 'delete': // eliminar completamente la convocatoria
                if ($call->delete($errors)) {
                    $log_text = 'El admin %s ha eliminado la convocatoria %s <span class="red">Completamente</span>';
                } else {
                    $log_text = 'Al admin %s le ha fallado al eliminar la convocatoria %s <span class="red">Completamente</span>';
                }
                break;
        }

        if (!empty($errors)) {
            Message::error(implode('<br />', $errors));
        }

        //si llega post, verificamos los datos y hacemos lo que se tenga que hacer
        if ($this->isPost() && $this->getPost('operation') && !empty($call)) {
            switch ($this->getPost('operation')) {
                case 'assign':
                    if (!empty($this->getPost('project'))) {
                        $registry = new Model\Call\Project;
                        $registry->id = $this->getPost('project');
                        $registry->call = $call->id;
                        if ($registry->save($errors)) {
                            Message::info('Proyecto seleccionado correctamente');

                            $projectData = Model\Project::getMini($this->getPost('project'));

                            // Evento feed
                            $log = new Feed();
                            $log->setTarget($projectData->id);
                            $log->populate('proyecto asignado a convocatoria desde admin', 'admin/calls/'.$call->id.'/projects',
                                \vsprintf('El admin %s ha asignado el proyecto %s a la convocatoria %s', array(
                                    Feed::item('user', $user->name, $user->id),
                                    Feed::item('project', $projectData->name, $projectData->id),
                                    Feed::item('call', $call->name, $call->id))
                                ));
                            $log->doAdmin('call');

                            // si la convocatoria está en campaña ( y el proyecto también), feed público
                            if ($call->status == 4 && $projectData->status == 3) {
                                $log->populate($projectData->name, '/project/'.$projectData->id,
                                    \vsprintf('Ha sido seleccionado en la convocatoria %s', array(
                                        Feed::item('call', $call->name, $call->id))
                                    ), $projectData->image);
                                $log->doPublic('projects');
                            }
                            unset($log);

                        } else {
                            Message::error('Fallo al seleccionar proyecto');
                        }
                    } else {
                        Message::error('No has seleccionado ningun proyecto para asignar a la convocatoria, no?');
                    }
                    break;
                case 'unassign':
                    if ($this->getPost('project')) {
                        $registry = new Model\Call\Project;
                        $registry->id = $this->getPost('project');
                        $registry->call = $call->id;
                        if ($registry->remove($errors)) {
                            Message::info('Proyecto desasignado correctamente');
                        } else {
                            Message::error('Fallo al desasignar proyecto');
                        }
                    } else {
                        Message::error('No has clickado ningun proyecto para desasignar, no?');
                    }
                    break;
            }
        }


        // si llega post de configuración de configuración
        if ($this->isPost() && $this->getPost('save-conf') && $call instanceof Model\Call ) {
            if ($call->setConf($this->getPost()->all(), $errors)) {
                $log_text = 'El admin %s ha <span class="red">cambiado la configuracion</span> de la convocatoria %s';
            } else {
                $log_text = 'Al admin %s le ha <span class="red">fallado al cambiar la configuracion</span> de la convocatoria %s';
                Message::info('Ha dado estos errores:<br/>'.implode('<br />', $errors));
            }
        }

         // si llega post de configuración económica
        if ($this->isPost() && $this->getPost('save-propconf') && $call instanceof Model\Call ) {
            if ($call->setDropconf($this->getPost()->all(), $errors)) {
                $log_text = 'El admin %s ha <span class="red">cambiado la configuracion financiera</span> de la convocatoria %s';
            } else {
                $log_text = 'Al admin %s le ha <span class="red">fallado al cambiar la configuracion financiera</span> de la convocatoria %s';
                Message::info('Ha dado estos errores:<br/>'.implode('<br />', $errors));
            }
        }


        if (isset($log_text)) {
            // Evento Feed
            $log = new Feed();
            $log->setTarget($call->id, 'call');
            $log_html = \vsprintf($log_text, array(
                    Feed::item('user', $user->name, $user->id),
                    Feed::item('call', $call->name, $call->id))
                );
            // Mensaje como el log
            Message::info($log_html);
            $log->populate('Gestion de una convocatoria desde el admin', '/admin/calls', $log_html);
            $log->doAdmin('admin');

            // publicos
            switch ($action) {
                case 'open': // se ha abierto para recibir proyectos
                    $log->populate($call->name, '/call/'.$call->id, Text::html('feed-new_call-opened'), $call->logo);
                    $log->doPublic();
                    break;
                case 'publish': // ha iniciado la campaña
                    $log->populate($call->name, '/call/'.$call->id, Text::html('feed-new_call-published'), $call->logo);
                    $log->doPublic();
                    break;
                case 'complete':
                    $log->unique = true;
                    $log->populate('Campaña terminada (cron)', '/admin/calls/'.$call->id,
                        \vsprintf('La campaña %s ha terminado con exito', array(
                            Feed::item('call', $call->name, $call->id))
                        ));
                    $log->doAdmin('call');
                    $log->populate($call->name, '/call/'.$call->id,
                        \vsprintf('La campaña %s ha terminado con éxito', array(
                            Feed::item('call', $call->name, $call->id))
                        ), $call->logo);
                    $log->doPublic('projects');
                    break;
            }
            unset($log);

            return $this->redirect('/admin/calls/list');
        }

        if ($action == 'add') {
            $callers = Model\User::getCallers();

            // cambiar fechas
            return array(
                    'folder' => 'calls',
                    'file' => 'add',
                    'callers' => $callers
            );
        }

        // lista de proyectos seleccionados
        if ($action == 'projects') {
            if (empty($call)) {
                Message::error('No se ha especificado ninguna convocatoria en la URL');
                return $this->redirect('/admin/calls/list');
            }
            // $filters = ($call->status > 3) ? array('published'=>true) : array('all'=>true);
            $filters = array('all'=>true); // (siempre todos)
            $projects   = Model\Call\Project::get($call->id, $filters);
            $status     = Model\Project::status();

            // a los seleccionados les añadimos el presupuesto y el máximo
            // esto lo pasamos a Model\Call\Project::get
            /*
            foreach ($projects as &$project) {
                // su presupuesto
                // calculo de mincost, maxcost solo si hace falta
                if(empty($project->mincost)) {
                    $costs = Model\Project::calcCosts($project->id);
                    $project->mincost = $costs->mincost;
                    $project->maxcost = $costs->maxcost;
                    // print_r($costs);die;
                }

                // le ponemos lo conseguido
                $project->invested = $project->amount_call + $project->amount_users;

                // y su máximo por proyecto
                $called = Model\Call\Project::called($project, $call, $project->amount_call);
                $project->maxproj = $called->maxproj;
            }
            */



            return array(
                    'folder' => 'calls',
                    'file' => 'projects',
                    'call' => $call,
                    'projects' => $projects,
                    // 'available' => $available,
                    'status' => $status
            );
        }

        if ($action == 'admins') {
            if (empty($call)) {
                Message::error('No se ha especificado ninguna convocatoria en la URL');
                return $this->redirect('/admin/calls');
            }

            if (isset($_GET['op']) && isset($_GET['user']) && in_array($_GET['op'], array('assign', 'unassign'))) {
                if ($call->$_GET['op']($_GET['user'])) {
                    // ok
                } else {
                    Message::error(implode('<br />', $errors));
                }
            }

            $call->admins = Model\Call::getAdmins($call->id);
            $admins = Model\User::getAdmins();

            return array(
                'folder' => 'calls',
                'file' => 'admins',
                'action' => 'admins',
                'call' => $call,
                'admins' => $admins
        );
        }

        if ($action == 'posts') {
            if (isset($_GET['op']) && isset($_GET['post']) && is_numeric($_GET['post']) && in_array($_GET['op'], array('save', 'remove'))) {

                $postId = $_GET['post'];
                // verificar que existe
                $thepost = Model\Blog\Post::get($postId);
                if ($_GET['op'] == 'save' && !$thepost instanceof Model\Blog\Post) {
                    Message::error("La entrada de blog id {$postId} NO es válida");
                } else {
                    // objeto
                    $post = new Model\Call\Post(array(
                        'id' =>  $postId,
                        'call' => $call->id
                    ));
                    if ($post->$_GET['op']($errors)) {
                        // ok
                    } else {
                        Message::error(implode('<br />', $errors));
                    }
                }

            }

            // entradas blog
            $call->posts = Model\Call\Post::get($call->id);

            return array(
                'folder' => 'calls',
                'file' => 'posts',
                'action' => 'posts',
                'call' => $call,
                'posts' => posts
            );
        }

        if ($action == 'conf') {
            if (empty($call)) {
                Message::error('No se ha especificado ninguna convocatoria en la URL');
                return $this->redirect('/admin/calls');
            }
            $conf = $call->getConf();

            return array(
                'folder' => 'calls',
                'file' => 'conf',
                'action' => 'list',
                'call' => $call,
                'conf' => $conf
            );
        }

        if ($action == 'dropconf') {

            return array(
                'folder' => 'calls',
                'file' => 'dropconf',
                'action' => 'list',
                'call' => $call
            );
        }

        // si es admin, solo las suyas
        if (isset($user->roles['admin'])) {
            $filters['admin'] = $user->id;
        }

        $calls = Model\Call::getList($filters);
        $status = Model\Call::status();
        $categories = Model\Call\Category::getAll();
        $callers = Model\User::getCallers();
        $admins = Model\Call::getAdmins();
        $orders = array(
            'name' => 'Nombre',
            'updated' => 'Apertura postulacion'
        );

        return array(
            'folder' => 'calls',
            'file' => 'list',
            'calls' => $calls,
            'filters' => $filters,
            'status' => $status,
            'categories' => $categories,
            'callers' => $callers,
            'admins' => $admins,
            'orders' => $orders
        );

    }

}
