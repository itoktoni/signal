@props([
    'id' => 'gaugeChart',
    'title' => 'Gauge Chart',
    'value' => 0,
    'min' => 0,
    'max' => 100,
    'height' => 300,
    'color' => '#5470c6',
    'unit' => '%'
])

<div class="chart-container">
    <div id="{{ $id }}" style="width: 100%; height: {{ $height }}px;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var chart = echarts.init(document.getElementById('{{ $id }}'));
    var option = {
        title: {
            text: '{{ $title }}',
            left: 'center',
            top: 20
        },
        series: [{
            name: 'Gauge',
            type: 'gauge',
            center: ['50%', '65%'],
            radius: '80%',
            startAngle: 200,
            endAngle: -20,
            min: {{ $min }},
            max: {{ $max }},
            splitNumber: 10,
            itemStyle: {
                color: '{{ $color }}'
            },
            progress: {
                show: true,
                width: 20
            },
            pointer: {
                show: true,
                length: '80%',
                width: 8
            },
            axisLine: {
                lineStyle: {
                    width: 20,
                    color: [
                        [0.3, '#fd666d'],
                        [0.7, '#37a2da'],
                        [1, '#67e0e3']
                    ]
                }
            },
            axisTick: {
                distance: -25,
                length: 8,
                lineStyle: {
                    color: '#fff',
                    width: 2
                }
            },
            splitLine: {
                distance: -25,
                length: 15,
                lineStyle: {
                    color: '#fff',
                    width: 3
                }
            },
            axisLabel: {
                color: '#464646',
                fontSize: 12,
                distance: -35
            },
            anchor: {
                show: true,
                showAbove: true,
                size: 25,
                itemStyle: {
                    borderWidth: 10
                }
            },
            title: {
                show: false
            },
            detail: {
                valueAnimation: true,
                fontSize: 30,
                offsetCenter: [0, '70%'],
                formatter: function (value) {
                    return value + '{{ $unit }}';
                }
            },
            data: [{
                value: {{ $value }}
            }]
        }]
    };
    chart.setOption(option);

    // Resize handler
    window.addEventListener('resize', function() {
        chart.resize();
    });
});
</script>