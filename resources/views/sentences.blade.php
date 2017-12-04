<html>
    <body>
        <table style="width:100%" border="1" cellspacing="0" bordercolor="#ccc">
            <?php $acc = 0; ?>
            @foreach ($sentences as $idx => $sentence)
                <tr>
                    <td colspan="3">{!! $sentence['tagged'] !!}</td>
                </tr>

                @foreach ($sentence['documents'] as $document)
                    <tr>
                        <td>{{$document['arg1']}}</td>
                        <td>{{$document['rel']}}</td>
                        <td>{{$document['arg2']}}</td>
                    </tr>
                @endforeach

                <tr>
                    <td></td>
                    <td></td>
                    <td><b>Subtotal: {{$sentence['subtotal']}} {{$acc = $acc + $sentence['subtotal']}}</b></td>
                </tr>
            @endforeach

            <tr>
                <td colspan="3"><h1>TOTAL: {{$total}}</h1></td>
            </tr>
        </table>
    </body>
</html>