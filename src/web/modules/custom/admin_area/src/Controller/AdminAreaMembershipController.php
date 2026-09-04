<?php

namespace Drupal\admin_area\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminAreaMembershipController extends ControllerBase {

  public function redirectToMembershipForm(NodeInterface $joint_programme) {
    if ($joint_programme->bundle() !== 'joint_programme') {
      throw new NotFoundHttpException();
    }

    $gid = admin_area_get_current_user_group_id();

    if (!$gid) {
      $this->messenger()->addError($this->t('Could not determine your group.'));
      return $this->redirect('view.admin_available_joint_programmes.page_1');
    }

    $url = Url::fromUserInput('/group/' . $gid . '/content/create/group_node%3Amember', [
      'query' => [
        'field_member_joint_programme' => $joint_programme->id(),
        'destination' => '/admin/admin-available-joint-programmes',
      ],
    ])->toString();

    return new RedirectResponse($url);
  }

}