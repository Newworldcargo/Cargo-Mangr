<div class="modal fade sales-modal" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="{{ $modalId }}-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form method="post" action="{{ $actionUrl }}">
            @csrf
            <div class="modal-header"><h2 class="modal-title" id="{{ $modalId }}-title">{{ $modalTitle }}</h2><button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
