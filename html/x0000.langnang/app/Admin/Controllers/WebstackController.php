<?php

namespace App\Admin\Controllers;

use App\Admin\Metrics\Examples;
use App\Http\Controllers\Controller;
use Dcat\Admin\Controllers\Dashboard;
use Dcat\Admin\Layout\Column;
use Dcat\Admin\Layout\Content;
use Dcat\Admin\Layout\Row;
use Dcat\Admin\Controllers\AdminController;
use Dcat\Admin\Form;
use Dcat\Admin\Models\Repositories\Menu;
use Dcat\Admin\Tree;
use Dcat\Admin\Widgets\Box;
use Dcat\Admin\Widgets\Form as WidgetForm;

class WebstackController extends AdminController
{
  public function title()
  {
    return trans('admin.menu');
  }

  public function index(Content $content)
  {
    return $content
      ->title($this->title())
      ->description(trans('admin.list'))
      ->body(function (Row $row) {
        $row->column(7, $this->treeView()->render());

        $row->column(5, function (Column $column) {
          $form = new WidgetForm();
          $form->action(admin_url('auth/menu'));

          $menuModel = config('admin.database.menu_model');
          $permissionModel = config('admin.database.permissions_model');
          $roleModel = config('admin.database.roles_model');

          $form->select('parent_id', trans('admin.parent_id'))->options($menuModel::selectOptions());
          $form->text('title', trans('admin.title'))->required();
          $form->icon('icon', trans('admin.icon'))->help($this->iconHelp());
          $form->text('uri', trans('admin.uri'));

          if ($menuModel::withRole()) {
            $form->multipleSelect('roles', trans('admin.roles'))->options($roleModel::all()->pluck('name', 'id'));
          }
          if ($menuModel::withPermission()) {
            $form->tree('permissions', trans('admin.permission'))
              ->expand(false)
              ->nodes((new $permissionModel())->allNodes());
          }
          $form->hidden('_token')->default(csrf_token());

          $form->width(9, 2);

          $column->append(Box::make(trans('admin.new'), $form));
        });
      });
  }

  /**
   * @return \Dcat\Admin\Tree
   */
  protected function treeView()
  {
    $menuModel = config('admin.database.menu_model');

    return new Tree(new $menuModel(), function (Tree $tree) {
      $tree->disableCreateButton();
      $tree->disableQuickCreateButton();
      $tree->disableEditButton();

      $tree->branch(function ($branch) {
        $payload = "<i class='fa {$branch['icon']}'></i>&nbsp;<strong>{$branch['title']}</strong>";

        if (!isset($branch['children'])) {
          if (url()->isValidUrl($branch['uri'])) {
            $uri = $branch['uri'];
          } else {
            $uri = admin_base_path($branch['uri']);
          }

          $payload .= "&nbsp;&nbsp;&nbsp;<a href=\"$uri\" class=\"dd-nodrag\">$uri</a>";
        }

        return $payload;
      });
    });
  }

  /**
   * Make a form builder.
   *
   * @return Form
   */
  public function form()
  {
    $menuModel = config('admin.database.menu_model');

    $relations = $menuModel::withPermission() ? ['permissions', 'roles'] : 'roles';

    return Form::make(new Menu($relations), function (Form $form) use ($menuModel) {
      $form->tools(function (Form\Tools $tools) {
        $tools->disableView();
      });

      $form->display('id', 'ID');

      $form->select('parent_id', trans('admin.parent_id'))->options(function () use ($menuModel) {
        return $menuModel::selectOptions();
      })->saving(function ($v) {
        return (int) $v;
      });
      $form->text('title', trans('admin.title'))->required();
      $form->icon('icon', trans('admin.icon'))->help($this->iconHelp());
      $form->text('uri', trans('admin.uri'));

      if ($menuModel::withRole()) {
        $form->multipleSelect('roles', trans('admin.roles'))
          ->options(function () {
            $roleModel = config('admin.database.roles_model');

            return $roleModel::all()->pluck('name', 'id');
          })
          ->customFormat(function ($v) {
            return array_column($v, 'id');
          });
      }
      if ($menuModel::withPermission()) {
        $form->tree('permissions', trans('admin.permission'))
          ->nodes(function () {
            $permissionModel = config('admin.database.permissions_model');

            return (new $permissionModel())->allNodes();
          })
          ->customFormat(function ($v) {
            if (!$v) {
              return [];
            }

            return array_column($v, 'id');
          });
      }

      $form->display('created_at', trans('admin.created_at'));
      $form->display('updated_at', trans('admin.updated_at'));
    })->saved(function (Form $form, $result) {
      if ($result) {
        return $form->location('auth/menu', __('admin.save_succeeded'));
      }

      return $form->location('auth/menu', [
        'message' => __('admin.nothing_updated'),
        'status'  => false,
      ]);
    });
  }

  /**
   * Help message for icon field.
   *
   * @return string
   */
  protected function iconHelp()
  {
    return 'For more icons please see <a href="http://fontawesome.io/icons/" target="_blank">http://fontawesome.io/icons/</a>';
  }
}
