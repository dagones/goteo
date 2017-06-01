<li data-name="<?= $this->image_name ?>">
    <div class="image" style="background-image:url(<?= $this->image_url ?>)"></div>
    <div class="options">
        <a class="default-image btn <?= $this->project->image->name == $this->image_name ? 'btn-pink' : 'btn-default' ?>"><i class="fa fa-star" title="<?= $this->text('dashboard-project-default-image') ?>"></i></a>
        <a class="delete-image btn btn-danger"><i class="fa fa-trash" title="<?= $this->text('dashboard-project-delete-image') ?>"></i></a>
    </div>
</li>