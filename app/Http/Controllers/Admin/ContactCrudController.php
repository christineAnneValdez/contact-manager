<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ContactRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Class ContactCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ContactCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Contact::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/contact');
        CRUD::setEntityNameStrings('contact', 'contacts');
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::addButtonFromView('top', 'sync_sevdesk', 'sync_sevdesk', 'beginning');
        CRUD::addButtonFromView('top', 'push_sevdesk', 'push_sevdesk', 'beginning');

        CRUD::column('name');
        CRUD::column('email');
        CRUD::addColumn([
            'name' => 'image',
            'type' => 'image',
            'label' => 'Contact Image',
            'disk' => 'public',
            'height' => '60px',
            'width'  => '60px',
        ]);
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
       CRUD::setValidation(ContactRequest::class);

        CRUD::field('name')->type('text')->label('Full Name');
        CRUD::field('email')->type('email')->label('Email Address');
        CRUD::addField([
            'name' => 'image',
            'type' => 'upload',
            'label' => 'Contact Image',
            'upload' => true,
            'disk' => 'public',
        ]);
    }

    protected function setupShowOperation()
    {
        CRUD::column('name');
        CRUD::column('email');
        CRUD::addColumn([
            'name'  => 'image',
            'label' => 'Contact Image',
            'type'  => 'image',
            'disk' => 'public',
            'height' => '120px',
            'width'  => '120px',
        ]);
        CRUD::column('created_at')->label('Created At');
        CRUD::column('updated_at')->label('Updated At');
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }

    public function syncSevDesk()
    {
        try {
            Artisan::call('sevdesk:sync-contacts');
            \Alert::success('sevDesk contacts synced successfully.')->flash();
        } catch (Throwable $e) {
            \Alert::error('sevDesk sync failed: '.$e->getMessage())->flash();
        }

        return redirect(backpack_url('contact'));
    }

    public function pushSevDesk()
    {
        try {
            Artisan::call('sevdesk:push-contacts');
            \Alert::success('Local contacts pushed to sevDesk successfully.')->flash();
        } catch (Throwable $e) {
            \Alert::error('sevDesk push failed: '.$e->getMessage())->flash();
        }

        return redirect(backpack_url('contact'));
    }
}

