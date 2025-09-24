@props([
    'id' => 'gaugeStageSpeedChart',
    'title' => 'Stage Speed Gauge',
    'value' => 0,
    'min' => 0,
    'max' => 240,
    'height' => 300,
    'unit' => 'km/h'
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
            name: 'Speed',
            type: 'gauge',
            center: ['50%', '60%'],
            radius: '90%',
            startAngle: 200,
            endAngle: -20,
            min: {{ $min }},
            max: {{ $max }},
            splitNumber: 12,
            itemStyle: {
                color: '#FFAB91'
            },
            progress: {
                show: true,
                width: 30
            },
            pointer: {
                show: false
            },
            axisLine: {
                lineStyle: {
                    width: 30,
                    color: [
                        [0.3, '#67e0e3'],
                        [0.7, '#37a2da'],
                        [1, '#fd666d']
                    ]
                }
            },
            axisTick: {
                distance: -45,
                length: 8,
                lineStyle: {
                    color: '#fff',
                    width: 2
                }
            },
            splitLine: {
                distance: -50,
                length: 30,
                lineStyle: {
                    color: '#fff',
                    width: 4
                }
            },
            axisLabel: {
                color: 'inherit',
                distance: 25,
                fontSize: 16
            },
            detail: {
                valueAnimation: true,
                formatter: function (value) {
                    return value + ' {{ $unit }}';
                },
                color: 'inherit',
                fontSize: 30,
                offsetCenter: [0, '40%']
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