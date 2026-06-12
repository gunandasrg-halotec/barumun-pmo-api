
# {{ $record["message"] }}

## Context 

@foreach ($record["context"] as $key=>$value )
    * {{ $key }} : {{  $value }}
@endforeach