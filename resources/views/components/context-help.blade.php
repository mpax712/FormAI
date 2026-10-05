@props(['title' => 'Como funciona esta área?'])
<details class="context-help mb-3">
    <summary>{{ $title }}</summary>
    <div class="context-help-body">{{ $slot }}</div>
</details>
