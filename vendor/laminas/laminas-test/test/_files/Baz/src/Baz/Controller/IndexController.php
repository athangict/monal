<?php

declare(strict_types=1);

namespace Baz\Controller;

use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
use RuntimeException;

class IndexController extends AbstractActionController
{
    /** @return array<string, string> */
    public function unittestsAction()
    {
        $this->getResponse()
            ->getHeaders()
            ->addHeaderLine('Content-Type: text/html')
            ->addHeaderLine('WWW-Authenticate: Basic realm="Laminas"');

        $numGet  = $this->getRequest()->getQuery()->get('num_get', 0);
        $numPost = $this->getRequest()->getPost()->get('num_post', 0);

        return ['num_get' => $numGet, 'num_post' => $numPost];
    }

    /** @return void */
    public function persistencetestAction()
    {
        $this->flashMessenger()->addMessage('test');
    }

    /** @return Response */
    public function redirectAction()
    {
        return $this->redirect()->toUrl('https://www.zend.com');
    }

    /** @return Response */
    public function redirectToRouteAction()
    {
        return $this->redirect()->toRoute('myroute');
    }

    /** @return void */
    public function exceptionAction()
    {
        throw new RuntimeException('Foo error !');
    }

    /** @return Response */
    public function customResponseAction()
    {
        $response = new Response();
        $response->setCustomStatusCode(999);

        return $response;
    }

    /** @return void */
    public function registerxpathnamespaceAction()
    {
    }

    public function childViewAction(): ViewModel
    {
        $child1 = new ViewModel();
        $child1->setTemplate('child1');
        $child2 = new ViewModel();
        $child2->setTemplate('child2');
        $child3 = new ViewModel();
        $child3->setTemplate('child3');
        $view = new ViewModel();
        $view->addChild($child1, 'child1');
        $child1->addChild($child3, 'child3');
        $view->addChild($child2, 'child2');
        return $view;
    }
}
