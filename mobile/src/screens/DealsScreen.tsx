import React, { useEffect, useState, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  TouchableOpacity,
  ActivityIndicator,
  RefreshControl,
  ScrollView,
  Alert,
} from 'react-native';
import { getApiClient } from '../api/client';
import type { Lead, Pipeline, PipelineStage } from '../types';

export default function DealsScreen() {
  const [pipelines, setPipelines] = useState<Pipeline[]>([]);
  const [selectedPipeline, setSelectedPipeline] = useState<Pipeline | null>(null);
  const [leads, setLeads] = useState<Lead[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const fetchPipelines = useCallback(async () => {
    try {
      const client = getApiClient();
      const res = await client.get('/pipelines');
      const data = res.data.data || [];
      setPipelines(data);
      if (data.length > 0 && !selectedPipeline) {
        const defaultPipeline = data.find((p: Pipeline) => p.is_default) || data[0];
        setSelectedPipeline(defaultPipeline);
      }
    } catch {
      Alert.alert('Error', 'Failed to load pipelines');
    }
  }, []);

  const fetchLeads = useCallback(async (pipelineId?: number) => {
    try {
      const client = getApiClient();
      const params: Record<string, string | number> = { limit: 50 };
      if (pipelineId) params.pipeline_id = pipelineId;
      const res = await client.get('/leads', { params });
      setLeads(res.data.data || []);
    } catch {
      Alert.alert('Error', 'Failed to load deals');
    }
  }, []);

  useEffect(() => {
    setLoading(true);
    Promise.all([fetchPipelines(), fetchLeads()]).finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    if (selectedPipeline) {
      setLoading(true);
      fetchLeads(selectedPipeline.id).finally(() => setLoading(false));
    }
  }, [selectedPipeline]);

  const handleRefresh = async () => {
    setRefreshing(true);
    await Promise.all([fetchPipelines(), fetchLeads(selectedPipeline?.id)]);
    setRefreshing(false);
  };

  const getLeadsForStage = (stageId: number) => {
    return leads.filter((l) => l.lead_pipeline_stage_id === stageId);
  };

  const formatCurrency = (value?: number) => {
    if (!value) return '';
    return `$${value.toLocaleString()}`;
  };

  const renderStageColumn = (stage: PipelineStage) => {
    const stageLeads = getLeadsForStage(stage.id);
    const totalValue = stageLeads.reduce((sum, l) => sum + (l.lead_value || 0), 0);

    return (
      <View key={stage.id} style={styles.stageColumn}>
        <View style={styles.stageHeader}>
          <Text style={styles.stageName}>{stage.name}</Text>
          <Text style={styles.stageCount}>
            {stageLeads.length} {stageLeads.length === 1 ? 'deal' : 'deals'}
          </Text>
          {totalValue > 0 && <Text style={styles.stageValue}>{formatCurrency(totalValue)}</Text>}
        </View>
        {stageLeads.map((lead) => (
          <View key={lead.id} style={styles.dealCard}>
            <Text style={styles.dealTitle} numberOfLines={1}>{lead.title}</Text>
            {lead.lead_value ? (
              <Text style={styles.dealValue}>{formatCurrency(lead.lead_value)}</Text>
            ) : null}
            {lead.person && <Text style={styles.dealContact}>{lead.person.name}</Text>}
          </View>
        ))}
        {stageLeads.length === 0 && (
          <Text style={styles.emptyStage}>No deals</Text>
        )}
      </View>
    );
  };

  if (loading && !refreshing) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#2563eb" />
      </View>
    );
  }

  const stages = selectedPipeline?.stages || [];

  return (
    <View style={styles.container}>
      {/* Pipeline selector */}
      {pipelines.length > 1 && (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} style={styles.pipelineSelector}>
          {pipelines.map((p) => (
            <TouchableOpacity
              key={p.id}
              style={[styles.pipelineChip, selectedPipeline?.id === p.id && styles.pipelineChipActive]}
              onPress={() => setSelectedPipeline(p)}
            >
              <Text
                style={[styles.pipelineChipText, selectedPipeline?.id === p.id && styles.pipelineChipTextActive]}
              >
                {p.name}
              </Text>
            </TouchableOpacity>
          ))}
        </ScrollView>
      )}

      {/* Kanban board - horizontal scroll */}
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={handleRefresh} />}
        style={styles.kanban}
      >
        {stages
          .sort((a, b) => a.sort_order - b.sort_order)
          .map(renderStageColumn)}
        {stages.length === 0 && (
          <View style={styles.center}>
            <Text style={styles.emptyText}>No pipeline stages configured</Text>
          </View>
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#f3f4f6' },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  pipelineSelector: {
    paddingHorizontal: 16,
    paddingVertical: 12,
    maxHeight: 52,
  },
  pipelineChip: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: '#e5e7eb',
    marginRight: 8,
  },
  pipelineChipActive: { backgroundColor: '#2563eb' },
  pipelineChipText: { fontSize: 14, color: '#374151', fontWeight: '500' },
  pipelineChipTextActive: { color: '#fff' },
  kanban: { flex: 1 },
  stageColumn: {
    width: 280,
    padding: 12,
    backgroundColor: '#f9fafb',
    borderRightWidth: 1,
    borderRightColor: '#e5e7eb',
  },
  stageHeader: {
    paddingBottom: 12,
    borderBottomWidth: 2,
    borderBottomColor: '#2563eb',
    marginBottom: 8,
  },
  stageName: { fontSize: 15, fontWeight: '700', color: '#111827' },
  stageCount: { fontSize: 12, color: '#6b7280', marginTop: 2 },
  stageValue: { fontSize: 13, color: '#059669', fontWeight: '600', marginTop: 2 },
  dealCard: {
    backgroundColor: '#fff',
    borderRadius: 8,
    padding: 12,
    marginBottom: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 1,
  },
  dealTitle: { fontSize: 14, fontWeight: '600', color: '#111827' },
  dealValue: { fontSize: 13, color: '#059669', fontWeight: '500', marginTop: 4 },
  dealContact: { fontSize: 12, color: '#6b7280', marginTop: 2 },
  emptyStage: { fontSize: 13, color: '#9ca3af', textAlign: 'center', paddingVertical: 20 },
  emptyText: { fontSize: 16, color: '#6b7280' },
});
