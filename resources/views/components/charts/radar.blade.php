@props([
    'id' => 'radarChart',
    'title' => 'Radar Chart',
    'indicators' => [],
    'data' => [],
    'height' => 300,
    'colors' => ['#5470c6', '#91cc75', '#fac858', '#ee6666', '#73c0de']
])

<div class="chart-container">
    <div id="{{ $id }}" style="width: 100%; height: {{ $height }}px;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var chart = echarts.init(document.getElementById('{{ $id }}'));
    var colors = @json($colors);

    var option = {
        title: {
            text: '{{ $title }}',
            left: 'center',
            top: 20
        },
        tooltip: {
            trigger: 'item'
        },
        legend: {
            data: @json($data).map(item => item.name),
            top: 50
        },
        radar: {
            indicator: @json($indicators),
            center: ['50%', '60%'],
            radius: '60%',
            startAngle: 90,
            splitNumber: 4,
            shape: 'circle',
            axisName: {
                color: '#333',
                fontSize: 12
            },
            splitArea: {
                areaStyle: {
                    color: ['rgba(250,250,250,0.3)', 'rgba(200,200,200,0.3)']
                }
            },
            axisLine: {
                lineStyle: {
                    color: '#ddd'
                }
            },
            splitLine: {
                lineStyle: {
                    color: '#ddd'
                }
            }
        },
        series: [{
            name: 'Radar',
            type: 'radar',
            data: @json($data).map(function(item, index) {
                return {
                    value: item.value,
                    name: item.name,
                    symbol: 'circle',
                    symbolSize: 8,
                    lineStyle: {
                        width: 2,
                        color: colors[index % colors.length]
                    },
                    areaStyle: {
                        color: colors[index % colors.length] + '40'
                    },
                    itemStyle: {
                        color: colors[index % colors.length]
                    }
                };
            }),
            emphasis: {
                lineStyle: {
                    width: 4
                }
            }
        }]
    };
    chart.setOption(option);

    // Resize handler
    window.addEventListener('resize', function() {
        chart.resize();
    });
});
</script>