@props([
    'id' => 'intradayChart',
    'title' => 'Intraday Chart',
    'data' => [],
    'height' => 300,
    'color' => '#5470c6'
])

<div class="chart-container">
    <div id="{{ $id }}" style="width: 100%; height: {{ $height }}px;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var chart = echarts.init(document.getElementById('{{ $id }}'));

    // Generate sample intraday data (9:30 AM to 4:00 PM)
    var data = @json($data).length > 0 ? @json($data) : [];
    if (data.length === 0) {
        var baseValue = 100;
        for (var i = 0; i < 390; i++) { // 6.5 hours * 60 minutes
            var time = new Date();
            time.setHours(9, 30 + i, 0, 0);
            var value = baseValue + Math.random() * 20 - 10;
            baseValue = value;
            data.push([time.getTime(), value.toFixed(2)]);
        }
    }

    var option = {
        title: {
            text: '{{ $title }}',
            left: 'center',
            top: 20
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'cross'
            },
            formatter: function (params) {
                var data = params[0];
                var time = new Date(data.data[0]);
                return time.toLocaleTimeString() + '<br/>Price: $' + data.data[1];
            }
        },
        grid: {
            left: '3%',
            right: '4%',
            bottom: '3%',
            top: '15%',
            containLabel: true
        },
        xAxis: {
            type: 'time',
            splitLine: {
                show: false
            },
            axisLabel: {
                formatter: function (value) {
                    var time = new Date(value);
                    return time.getHours() + ':' + (time.getMinutes() < 10 ? '0' : '') + time.getMinutes();
                }
            }
        },
        yAxis: {
            type: 'value',
            scale: true,
            splitLine: {
                show: false
            },
            axisLabel: {
                formatter: '${value}'
            }
        },
        dataZoom: [{
            type: 'inside',
            start: 0,
            end: 100
        }, {
            show: true,
            type: 'slider',
            top: '90%',
            start: 0,
            end: 100
        }],
        series: [{
            name: 'Price',
            type: 'line',
            smooth: true,
            symbol: 'none',
            lineStyle: {
                color: '{{ $color }}',
                width: 1
            },
            areaStyle: {
                color: {
                    type: 'linear',
                    x: 0,
                    y: 0,
                    x2: 0,
                    y2: 1,
                    colorStops: [{
                        offset: 0, color: '{{ $color }}20'
                    }, {
                        offset: 1, color: '{{ $color }}05'
                    }]
                }
            },
            data: data
        }]
    };
    chart.setOption(option);

    // Resize handler
    window.addEventListener('resize', function() {
        chart.resize();
    });
});
</script>