@props([
    'id' => 'radialBarChart',
    'title' => 'Radial Bar Chart',
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
            trigger: 'item',
            formatter: '{a} <br/>{b}: {c}%'
        },
        legend: {
            data: @json($data).map(item => item.name),
            top: 50
        },
        series: [{
            name: 'Priority',
            type: 'pie',
            radius: ['20%', '80%'],
            center: ['50%', '55%'],
            roseType: 'area',
            itemStyle: {
                borderRadius: 8
            },
            data: @json($data).map(function(item, index) {
                return {
                    value: item.value,
                    name: item.name,
                    itemStyle: {
                        color: colors[index % colors.length]
                    },
                    label: {
                        show: true,
                        position: 'inside',
                        formatter: '{d}%',
                        fontSize: 12,
                        fontWeight: 'bold'
                    }
                };
            }),
            emphasis: {
                itemStyle: {
                    shadowBlur: 10,
                    shadowOffsetX: 0,
                    shadowColor: 'rgba(0, 0, 0, 0.5)'
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