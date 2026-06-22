<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">回调日志</h2>
    </div>

    <div class="card-wrapper">
      <div class="filter-bar">
        <el-form :inline="true" :model="filterForm" @submit.prevent="loadList">
          <el-form-item label="承运商">
            <el-select v-model="filterForm.carrier_code" placeholder="全部" clearable @change="handleFilterChange">
              <el-option v-for="c in carriers" :key="c.carrier_code" :label="c.carrier_name" :value="c.carrier_code" />
            </el-select>
          </el-form-item>
          <el-form-item label="状态">
            <el-select v-model="filterForm.process_status" placeholder="全部" clearable @change="handleFilterChange">
              <el-option label="待处理" :value="0" />
              <el-option label="处理成功" :value="1" />
              <el-option label="处理失败" :value="2" />
              <el-option label="重试中" :value="3" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="loadList">查询</el-button>
            <el-button @click="resetFilter">重置</el-button>
          </el-form-item>
        </el-form>
      </div>

      <el-table :data="list" v-loading="loading" border stripe>
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="carrier_code" label="承运商" width="120" />
        <el-table-column prop="request_method" label="方法" width="70" align="center" />
        <el-table-column prop="request_url" label="请求URL" min-width="200" show-overflow-tooltip />
        <el-table-column prop="process_status" label="处理状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="logStatusType(row.process_status)" size="small">
              {{ logStatusLabel(row.process_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="error_message" label="错误信息" min-width="200" show-overflow-tooltip />
        <el-table-column prop="retry_count" label="重试" width="60" align="center" />
        <el-table-column prop="ip_address" label="来源IP" width="130" />
        <el-table-column prop="processing_time_ms" label="耗时(ms)" width="90" align="center" />
        <el-table-column prop="created_at" label="时间" width="170" />
        <el-table-column label="操作" width="80" fixed="right">
          <template #default="{ row }">
            <el-button
              v-if="row.process_status === 2 || row.process_status === 3"
              size="small"
              type="warning"
              @click="handleRetry(row)"
            >重试</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-if="total > 0"
        style="margin-top: 16px; justify-content: flex-end"
        background
        layout="total, sizes, prev, pager, next"
        :total="total"
        :page-size="pageSize"
        :current-page="page"
        :page-sizes="[10, 20, 50]"
        @size-change="(s) => { pageSize = s; loadList() }"
        @current-change="(p) => { page = p; loadList() }"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import request from '@/utils/request'

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const carriers = ref([])

const filterForm = reactive({
  carrier_code: '',
  process_status: '',
})

const logStatusType = (s) => ({ 0: 'info', 1: 'success', 2: 'danger', 3: 'warning' }[s] || 'info')
const logStatusLabel = (s) => ({ 0: '待处理', 1: '成功', 2: '失败', 3: '重试中' }[s] || '未知')

const loadCarriers = async () => {
  try {
    const res = await request.get('/carriers', { params: { page_size: 100 } })
    carriers.value = res.items || []
  } catch (e) { /* ignore */ }
}

const loadList = async () => {
  loading.value = true
  try {
    const res = await request.get('/tracking/logs', {
      params: { ...filterForm, page: page.value, page_size: pageSize.value },
    })
    list.value = res.items || []
    total.value = res.pagination ? res.pagination.total : (res.total || 0)
  } catch (e) { /* ignore */ }
  loading.value = false
}

const handleFilterChange = () => {
  page.value = 1
  loadList()
}

const resetFilter = () => {
  filterForm.carrier_code = ''
  filterForm.process_status = ''
  page.value = 1
  loadList()
}

const handleRetry = async (row) => {
  try {
    await request.post(`/tracking/retry/${row.id}`)
    ElMessage.success('重试成功')
    loadList()
  } catch (e) {
    ElMessage.error('重试失败')
  }
}

onMounted(() => {
  loadCarriers()
  loadList()
})
</script>
