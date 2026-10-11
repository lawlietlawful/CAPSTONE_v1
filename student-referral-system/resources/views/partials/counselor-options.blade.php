{{--
    <option>s for a counselor <select>. Expects $counselors (every counselor,
    deactivated ones included) and:

      $mode     'filter'   — browsing history: everyone, deactivated labelled
                'new'      — a new assignment: active counselors only
                'existing' — re-saving a current assignment: active counselors
                             plus the current assignee even if deactivated, so
                             saving the form can never silently unassign them
      $selected the value to pre-select (the current assignee, old() input,
                or the active filter); for 'existing' it is also the one
                deactivated counselor kept in the list
      $skip     (optional) an id to leave out, e.g. "Me" already offered
--}}
@php $skip = $skip ?? null; @endphp
@foreach($counselors as $c)
    @continue($skip !== null && (int) $c->id === (int) $skip)
    @continue($c->deactivated_at && $mode === 'new')
    @continue($c->deactivated_at && $mode === 'existing' && (string) $c->id !== (string) $selected)
    <option value="{{ $c->id }}" @selected((string) $c->id === (string) $selected)>{{ $c->name }}{{ $c->deactivated_at ? ' (deactivated)' : '' }}</option>
@endforeach
