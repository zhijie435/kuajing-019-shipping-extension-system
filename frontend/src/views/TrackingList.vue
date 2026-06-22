<template>
  <div>
    <div class="page-header">
      <h2 class="page-title">轨迹回传监控</h2>
    </div>

    <el-row :gutter="16" style="margin-bottom:16px">
      <el-col :span="6">
        <div class="stat-card">
          <div class="stat-label">今日回传总量</div>
          <div class="stat-value">{{ stats.todayTotal || 0 }}</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card">
          <div class="stat-label">异常轨迹</div>
          <div class="stat-value danger">{{ stats.exceptionCount || 0 }}</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card">
          <div class="stat-label">未同步</div>
          <div class="stat-value warning">{{ stats.unsyncedCount || 0 }}</div>
        </div>
      </el-col>
      <el-col :span="6">
        <div class="stat-card">
          <div class="stat-label">回调失败</div>
          <div class="stat-value danger">{{ stats.failedCallbackCount || 0 }}</div>
        </div>
      </el-col>
    </el-row>

    <div class="card-wrapper">
      <div class="filter-bar">
        <el-form :inline="true" :model="filterForm" @submit.prevent="loadList">
          <el-form-item label="运单号">
            <el-input v-model="filterForm.tracking_no" placeholder="运单号" clearable @clear="handleFilterChange" />
          </el-form-item>
          <el-form-item label="承运商">
            <el-select v-model="filterForm.carrier_code" placeholder="全部" clearable @change="handleFilterChange">
              <el-option v-for="c in carriers" :key="c.carrier_code" :label="c.carrier_name" :value="c.carrier_code" />
            </el-select>
          </el-form-item>
          <el-form-item label="状态">
            <el-select v-model="filterForm.standard_status" placeholder="全部" clearable @change="handleFilterChange">
              <el-option v-for="s in meta.standard_statuses" :key="s.value" :label="s.label" :value="s.value" />
            </el-select>
          </el-form-item>
          <el-form-item label="同步">
            <el-select v-model="filterForm.is_synced" placeholder="全部" clearable @change="handleFilterChange">
              <el-option label="未同步" :value="0" />
              <el-option label="已同步" :value="1" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="loadList">查询</el-button>
            <el-button @click="resetFilter">重置</el-button>
          </el-form-item>
        </el-form>
      </div>

      <el-table :data="list" v-loading="loading" border stripe>
        <el-table-column prop="tracking_no" label="运单号" width="160" />
        <el-table-column prop="carrier_name" label="承运商" width="120" />
        <el-table-column prop="event_code" label="事件编码" width="110" />
        <el-table-column prop="event_desc" label="事件描述" min-width="150" show-overflow-tooltip />
        <el-table-column prop="event_time" label="事件时间" width="170" />
        <el-table-column prop="event_location" label="地点" width="140" show-overflow-tooltip />
        <el-table-column prop="standard_status" label="标准状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.standard_status)" size="small">
              {{ getMetaLabel(meta.standard_statuses, row.standard_status) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="is_synced" label="同步" width="70" align="center">
          <template #default="{ row }">
            <el-icon :color="row.is_synced ? '#67c23a' : '#e6a23c'">
              <component :is="row.is_synced ? 'CircleCheck' : 'Clock'" />
            </el-icon>
          </template>
        </el-table-column>
        <el-table-column prop="order_no" label="订单号" width="140" show-overflow-tooltip />
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
import request from '@/utils/request'

const loading = ref(false)
const list = ref([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const carriers = ref([])
const stats = reactive({
  todayTotal: 0,
  exceptionCount: 0,
  unsyncedCount: 0,
  failedCallbackCount: 0,
})

const meta = reactive({ standard_statuses: [] })

const filterForm = reactive({
  tracking_no: '',
  carrier_code: '',
  standard_status: '',
  is_synced: '',
})

const getMetaLabel = (arr, val) => {
  const item = arr.find(i => i.value === val)
  return item ? item.label : String(val)
}

const statusTagType = (status) => {
  const map = {
    PICKED_UP: 'info',
    IN_TRANSIT: '',
    OUT_FOR_DELIVERY: 'warning',
    DELIVERED: 'success',
    EXCEPTION: 'danger',
    UNKNOWN: 'info',
  }
  return map[status] || 'info'
}

const loadMeta = async () => {
  try {
    const res = await request.get('/meta/all')
    meta.standard_statuses = res.standard_statuses || []
  } catch (e) { /* ignore */ }
}

const loadCarriers = async () => {
  try {
    const res = await request.get('/carriers', { params: { page_size: 100 } })
    carriers.value = res.items || []
  } catch (e) { /* ignore */ }
}

const loadList = async () => {
  loading.value = true
  try {
    const res = await request.get('/tracking/events', {
      params: { ...filterForm, page: page.value, page_size: pageSize.value },
    })
    list.value = res.items || []
    total.value = res.pagination ? res.pagination.total : (res.total || 0)
  } catch (e) { /* ignore */ }
  loading.value = false
}

const loadStats = async () => {
  try {
    const res = await request.get('/tracking/stats')
    stats.unsyncedCount = res.unsynced_count || 0
    stats.failedCallbackCount = res.failed_callback_count || 0
  } catch (e) { /* ignore */ }
}

const handleFilterChange = () => {
  page.value = 1
  loadList()
}

const resetFilter = () => {
  filterForm.tracking_no = ''
  filterForm.carrier_code = ''
  filterForm.standard_status = ''
  filterForm.is_synced = ''
  page.value = 1
  loadList()
}

onMounted(() => {
  loadMeta()
  loadCarriers()
  loadList()
  loadStats()
})
</script>
